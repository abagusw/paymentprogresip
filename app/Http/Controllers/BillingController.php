<?php

namespace App\Http\Controllers;

use App\Models\Clients;
use App\Models\Invoices;
use App\Models\InvoicesRecurring;
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

    public function LoadXenditSetting(): array {
        
        $setting_xendit_secret_key_billing = Settings::where('setting_key', 'xnd_api_key_development_billing_api')->first();
        $setting_xnd_success_redirect_url = Settings::where('setting_key', 'xnd_success_redirect_url')->first();
        $setting_xnd_failure_redirect_url = Settings::where('setting_key', 'xnd_failure_redirect_url')->first();
        $xendit_secret_key_billing = $setting_xendit_secret_key_billing->value_text;
        $success_redirect_url = $setting_xnd_success_redirect_url->value_text;
        $failure_redirect_url = $setting_xnd_failure_redirect_url->value_text;
        return [
            "xendit_secret_key_billing" => $xendit_secret_key_billing,
            "success_redirect_url" => $success_redirect_url,
            "failure_redirect_url" => $failure_redirect_url,
        ];
    }

    public function ProcessPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_email' => 'required|max:225|email',
            'client_name' => 'required|max:225',
            'client_phone' => 'required',
            'client_address' => 'required',
            'group_id' => 'required', // 1,2 or 3
            'number_of_months' => 'required|numeric', // 1,3,6 or 12
            'total_payment_amount' => 'required|numeric',
            'is_recurring' => 'max:225',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'Failed', 'message' => $validator->messages()->first()]);
        }
        $request_data = $request->all();
        
        $is_recurring = $request_data['is_recurring'] == 'Y';

        $client_email = $request_data['client_email'];
        $client = Clients::where('client_email', $client_email)->first();
        $client_data = [
            "external_client_id" => 'C'.uniqid(),
            "client_email" => $request_data["client_email"],
            "client_name" => $request_data["client_name"],
            "client_phone" => $request_data["client_phone"],
            "client_address" => $request_data["client_address"],
        ];
        $client_id = null;
        if ($client != null) {
            Clients::where('client_email',$client_email)->update($client_data);
            $client_id = $client->client_id;
        } else {
            $client_id = Clients::insertGetId($client_data);
        }   

        $xset = $this->LoadXenditSetting();

        $this->SyncClientXendit($client_id, $xset); // sync xendit client

        if ($is_recurring) {
            $responsexnd = $this->CreateRecurringPlanXendit($client_id, $request_data, $xset); //create xendit recurring
            if ($responsexnd['success'] == false) {
                return response()->json(['status' => 'Failed', 'message' => $responsexnd['message'], 'data' => $responsexnd['data']]);
            }
        } else {
            $responsexnd = $this->CreateInvoice($client_id, $request_data, $xset); //create xendit invoice
            if ($responsexnd['success'] == false) {
                return response()->json(['status' => 'Failed', 'message' => $responsexnd['message']]);
            }
        }
        return response()->json(['status' => 'Success', 'message' => 'billing has created successfully', 'data'=>$responsexnd['data']]);
    }

    public function SyncClientXendit($client_id, $xset)
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
                    "street_line1" => $client->client_address,
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
            Xendit::setApiKey($xset["xendit_secret_key_billing"]);
            $createCustomer = \Xendit\Customers::createCustomer($data);
            if ($createCustomer['id']) {
                Clients::where('client_id', $client_id)->update([
                    'client_xendit_cusid' => $createCustomer['id'],
                ]);
            }
        } else { // update xendit customer
            $apiKey = $xset["xendit_secret_key_billing"];
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
    
    public function CreateInvoice($client_id, $request_data, $xset) {
        $success = true;
        $message = '';
        $data = array();
        $invoice_data = [
            "external_invoice_id" => 'I'.uniqid(),
            "client_id" => $client_id,
            "amount" => $request_data['total_payment_amount'],
            "group_id" => $request_data['group_id'],
            "number_of_months" => $request_data['number_of_months'],
        ];
        $invoice_id = Invoices::insertGetId($invoice_data);
        if ($invoice_id) {
            $client = Clients::where('client_id', $client_id)->first();  
            
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
                'success_redirect_url' => $xset["success_redirect_url"],
                'failure_redirect_url' => $xset["failure_redirect_url"],
                'currency' => 'IDR'
              ];

            Xendit::setApiKey($xset["xendit_secret_key_billing"]);
            $createInvoice = \Xendit\Invoice::create($params);
            if ($createInvoice['id']) {
                $data = [
                    'xendit_invoice_id' => $createInvoice['id'],
                    'xendit_invoice_status' => $createInvoice['status'],
                    'xendit_invoice_url' => $createInvoice['invoice_url'],
                    'xendit_invoice_expired' => date('Y-m-d H:i:s', strtotime($createInvoice['expiry_date'])),
                ];
                Invoices::where('invoice_id', $invoice_id)->update($data);
            } else {                
                $success = false;
                $message = 'Cannot create xendit invoice';
            }
        } else {
            $success = false;
            $message = 'Cannot create invoice';
        }

        return ['success'=> $success, 'message'=> $message, 'data' => $data];
    }

    public function CreateRecurringPlanXendit($client_id, $request_data, $xset) {
        $success = true;
        $message = '';
        $data = array();
        $interval = "MONTH";
        $invoice_data = [
            "external_invoice_recurring_id" => 'IR'.uniqid(),
            "external_invoice_schedule_id" => 'IRS'.uniqid(),
            "client_id" => $client_id,
            "amount" => $request_data['total_payment_amount'],
            "group_id" => $request_data['group_id'],
            "number_of_months" => $request_data['number_of_months'],
        ];
        $invoice_recurring_id = InvoicesRecurring::insertGetId($invoice_data);
        
        if ($invoice_recurring_id) {
            date_default_timezone_set('Asia/Jakarta');
            $datetimenow = date('Y-m-d H:i:s');
            $datetimenow_spt = explode(' ', $datetimenow);
            $datenow = $datetimenow_spt[0];
            $timenow = $datetimenow_spt[1];
            $anchor_date = $datenow . 'T' . $timenow . 'Z';
            $client = Clients::where('client_id', $client_id)->first(); 
            
            $invoice_recurring = InvoicesRecurring::where("invoice_recurring_id", $invoice_recurring_id)->first();

            $data = [
                    "reference_id" => $invoice_recurring->external_invoice_recurring_id,
                    "customer_id" => $client->client_xendit_cusid,
                    "recurring_action" => "PAYMENT",
                    "currency" => "IDR",
                    "amount" => (double)$invoice_recurring->amount,
                    "payment_methods" => [],
                    "schedule" => [
                      "reference_id" => $invoice_recurring->external_invoice_schedule_id,
                      "interval" => $interval,
                      "interval_count" => (int) $invoice_recurring->number_of_months,
                      "total_recurrence" => 12,
                      "anchor_date" => $anchor_date,
                      "retry_interval" => "DAY",
                      "retry_interval_count" => 2,
                      "total_retry" => 2,
                      "failed_attempt_notifications" => [1,2]
                    ],
                    "immediate_action_type" => "FULL_AMOUNT",
                    "notification_config" => [
                      "recurring_created" => ["WHATSAPP","EMAIL"],
                      "recurring_succeeded" => ["WHATSAPP","EMAIL"],
                      "recurring_failed" => ["WHATSAPP","EMAIL"],
                      "locale" => "en"],
                    "failed_cycle_action" => "STOP",
                    "payment_link_for_failed_attempt" => false,
                    "metadata" => null,
                    "description" => "Sekutu ProgreSIP",
                    "items" => [
                          [
                              "type" => 'DIGITAL_PRODUCT',
                              "name" => 'GROUP - ' . $invoice_recurring->group_id,
                              "net_unit_amount" => (double)$invoice_recurring->amount,
                              "quantity" =>  1,
                              "url" => $xset["success_redirect_url"],
                              "category" => "Newsletter",
                              "subcategory" => "Newsletter"
                          ]
                      ],
                    "success_return_url" => $xset["success_redirect_url"],
                    "failure_return_url" => $xset["failure_redirect_url"]
                    ];

            try {
                $apiKey = $xset["xendit_secret_key_billing"];
                $url = "https://api.xendit.co/recurring/plans";
                $headers = [];
                $headers[] = "Content-Type: application/json";
            
                $curl = curl_init();

                $payload = json_encode($data);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($curl, CURLOPT_USERPWD, $apiKey.":");
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            
                $curl_result = curl_exec($curl);
                $response_plan = json_decode($curl_result);
            
                $data_invoice = [
                    'xnd_recurring_plan_id' => $response_plan->id,
                    'xnd_recurring_schedule_id' => $response_plan->schedule->id,
                    'xnd_recurring_plan_status' => $response_plan->status,
                    'xendit_invoice_url' => $response_plan->actions[0]->url,
                    'xendit_invoice_expired' => date('Y-m-d H:i:s', strtotime($datetimenow. ' + 1 day')),
                ];
                InvoicesRecurring::where('invoice_recurring_id', $invoice_recurring_id)->update($data_invoice);
            } catch (\Exception $e) {
                $success = false;
                $message = 'Cannot create xendit invoice recurring';
                $data = ['message'=>$e->getMessage(),'curl_result'=>$curl_result];
            }
        } else {
            $success = false;
            $message = 'Cannot create invoice recurring';
        }

        return ['success'=> $success, 'message'=> $message, 'data' => $data];
    }
}
