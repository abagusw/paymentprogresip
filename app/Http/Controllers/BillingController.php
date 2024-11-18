<?php

namespace App\Http\Controllers;

use App\Models\Clients;
use App\Models\Invoices;
use App\Models\Settings;
use Xendit\Xendit;
use Illuminate\Http\Request;
use Validator;

class BillingController extends Controller
{   

    public function index() {
        echo json_encode([
            "success" => true,
            "message" => "API Okay"
        ]);
    }

    public function ProcessPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_email' => 'required|max:225|email',
            'client_name' => 'required|max:225',
            'client_phone' => 'required',
            'group_id' => 'required', // 1,2 or 3
            'number_of_months' => 'required|numeric', // 1,3,6 or 12
            'total_payment_amount' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'Failed', 'message' => $validator->messages()->first()]);
        }
        $request_data = $request->all();
        
        $client_email = $request_data['client_email'];
        $client = Clients::where('client_email', $client_email)->first();
        $client_data = $request_data;
        unset($client_data['number_of_months']);
        unset($client_data['group_id']);
        unset($client_data['total_payment_amount']);
        $client_data['external_client_id'] = 'C'.uniqid();
        $client_id = null;
        if ($client != null) {
            Clients::where('client_email',$client_email)->update($client_data);
            $client_id = $client->client_id;
        } else {
            $client_id = Clients::insertGetId($client_data);
        }   

        $setting_key = Settings::where('setting_key', 'xnd_api_key_development_billing_api')->first();
        $xendit_secret_key_billing = $setting_key->value_text;
        Xendit::setApiKey($xendit_secret_key_billing);

        $this->SyncClientXendit($client_id, $xendit_secret_key_billing); // sync xendit client

        $responsexnd = $this->CreateInvoice($client_id, $request_data); //create xendit invoice
        if ($responsexnd['success'] == false) {
            return response()->json(['status' => 'Failed', 'message' => $responsexnd['message']]);
        }
        return response()->json(['status' => 'Success', 'message' => 'billing has created successfully', 'data'=>$responsexnd['data']]);
    }

    public function SyncClientXendit($client_id, $xendit_secret_key)
    {
        $client = Clients::where('client_id', $client_id)->first();
        $client_phone = substr_replace($client->client_phone, "+62", 0, 1);
        $data = [
            "reference_id" => $client->external_client_id,
            "type" => "INDIVIDUAL",
            "individual_detail" => [
                "given_names" => $client->client_name,
                "surname" => $client->client_name,
                "nationality"=> 'ID',
            ],
            "email" => $client->client_email,
            "mobile_number" => $client_phone,
            "phone_number" => $client_phone,
            "addresses" => [
                [
                    "street_line1" => 'Default System',
                    "street_line2" => 'Default System',
                    "city" => 'Default System',
                    "province_state" => 'Default System',
                    "postal_code" => 'Default System',
                    "country" => 'ID',
                    "category" => "HOME",
                    "is_primary"=> true
                ]
            ],
            "identity_accounts" => [
                [
                    "type" => 'SOCAL_MEDIA',
                    "properties" => [
                        "account_id" => $client->client_email
                    ]
                ]                    
            ],
            "kyc_documents" => [
                [
                    "country" => 'ID',
                    "type" => 'IDENTITY_CARD'
                ]
            ],
            "api-version" => '2020-10-31'
            ];
    
        if (!$client->client_xendit_cusid) { // create xendit customer
            $createCustomer = \Xendit\Customers::createCustomer($data);
            if ($createCustomer['id']) {
                Clients::where('client_id', $client_id)->update([
                    'client_xendit_cusid' => $createCustomer['id'],
                ]);
            }
        } else { // update xendit customer
            $apiKey = $xendit_secret_key;
            $url = "https://api.xendit.co/customers/".$client->client_xendit_cusid;
            $headers = [];
            $headers[] = "Content-Type: application/json";
          
            $curl = curl_init();
            unset($data['reference_id'],$data['type'],$data['api-version']);
          
            $payload = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_USERPWD, $apiKey.":");
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          
            $result = curl_exec($curl);
        }
    }
    
    public function CreateInvoice($client_id, $request_data) {
        $success = true;
        $message = '';
        $data = array();
        $invoice_data = [
            "external_invoice_id" => 'I'.uniqid(),
            "client_id" => $client_id,
            "amount" => $request_data->total_payment_amount,
            "group_id" => $request_data->group_id,
            "number_of_months" => $request_data->number_of_months,
        ];
        $invoice_id = Invoices::insertGetId($invoice_data)->first();
        if ($invoice_id) {
            $client = Clients::where('client_id', $client_id)->first();  
            $setting_xnd_success_redirect_url = Settings::where('setting_key', 'xnd_success_redirect_url_api')->first();
            $setting_xnd_failure_redirect_url = Settings::where('setting_key', 'xnd_failure_redirect_url_api')->first();
            $success_redirect_url = $setting_xnd_success_redirect_url->value_text;
            $failure_redirect_url = $setting_xnd_failure_redirect_url->value_text;
            
            $invoice = Invoices::where("invoice_id", $invoice_id)->first();
            $client_phone = substr_replace($client->client_phone, "+62", 0, 1);
            
            $params = [ 
                'external_id' => $invoice->external_invoice_id,
                'amount' => $invoice->amount,
                'description' => 'Payment of Invoice '.$invoice->external_invoice_id.' for Progresip ',
                'invoice_duration' => 86400,
                'customer' => [
                    'given_names' => $client->client_name,
                    'surname' => $client->client_name,
                    'email' => $client->client_email,
                    'mobile_number' => $client_phone,
                    'addresses' => [
                        [
                            'city' => "Default System",
                            'country' => 'ID',
                            'postal_code' => "Default System",
                            'state' => "Default System",
                            'street_line1' => "Default System",
                            'street_line2' => "Default System"
                        ]
                    ]
                ],
                'customer_notification_preference' => [
                    'invoice_created' => [
                        'whatsapp',
                        'sms',
                        'email',
                        'viber'
                    ],
                    'invoice_reminder' => [
                        'whatsapp',
                        'sms',
                        'email',
                        'viber'
                    ],
                    'invoice_paid' => [
                        'whatsapp',
                        'sms',
                        'email',
                        'viber'
                    ],
                    'invoice_expired' => [
                        'whatsapp',
                        'sms',
                        'email',
                        'viber'
                    ]
                ],
                'success_redirect_url' => $success_redirect_url,
                'failure_redirect_url' => $failure_redirect_url,
                'currency' => 'IDR'
              ];
              echo "ERROR TRACK";
            $createInvoice = \Xendit\Invoice::create($params);
            if ($createInvoice['id']) {
                $data = array(
                        'xendit_invoice_id' => $createInvoice['id'],
                        'xendit_invoice_status' => $createInvoice['status'],
                        'xendit_invoice_url' => $createInvoice['invoice_url'],
                        'xendit_invoice_expired' => date('Y-m-d H:i:s', strtotime($createInvoice['expiry_date'])),
                    );
                Invoices::where('invoice_id', $invoice_id)->update($data);
            } else {                
                $success = false;
                $message = 'Cannot create xendit payment';
            }
        } else {
            $success = false;
            $message = 'Invalid payment method';
        }

        return ['success'=> $success, 'message'=> $message, 'data' => $data];
    }

}
