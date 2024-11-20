<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

use App\Models\Clients;
use App\Models\Invoices;
use App\Models\InvoicesRecurring;
use Illuminate\Support\Str;
use Xendit\Xendit;
use Snowfire\Beautymail\Beautymail;
use PDF;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->call(function() {
        //     $reponse = $this->CheckAndSendReminder();
        //     Log::info(['CRON NAME'=>'Check And Send Reminder', 'CRON RESPONSE'=>$reponse]);
        // })->daily();
        $schedule->call(function() {
            $reponse = $this->CancelPendingPayment();
            Log::info(['CRON NAME'=>'Cancel Pending Payment', 'CRON RESPONSE'=>$reponse]);
        })->everyFourHours();
        // $schedule->call(function() {
        //     $reponse = $this->UpdateSubscriptionExpired();
        //     Log::info(['CRON NAME'=>'Update Subscription Expired', 'CRON RESPONSE'=>$reponse]);
        // })->everyFourHours();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /** CRON FUNCTION TO CHANGE STATUS OF SUBSCRIPTION HAVING PENDING PAYMENT */
    public function CancelPendingPayment()
    {                     
        $dateNow = date('Y-m-d H:i:s');
        // Delete Expired
        Clients::whereNotNull('subscribed_end')->where('subscribed_end','<', $dateNow)->where('subscribed_status', 1)->update(['subscribed_status'=>0]);
    }

    /** CRON FUNCTION TO SEND REMINDER WHEN SUBSCRIPTION IS 7, 3 AND 1 DAYS BEFORE EXPIRED */
    public function CheckAndSendReminder()
    {
        $setting_key = Setting::where('setting_key', 'xnd_api_key_development_billing_api')->first();
        $xendit_secret_key_billing = $setting_key->setting_value;
        Xendit::setApiKey($xendit_secret_key_billing);
        
        $message = '';
        $res1 = $this->SendReminder(7);
        if(sizeof($res1) == 0){
            $message = 'No Reminder for 7 days(First);';
        }
        $res2 = $this->SendReminder(3);
        if(sizeof($res2) == 0){
            $message .= 'No Reminder for 3 days(Second);';
        }
        $res3 = $this->SendReminder(1);
        if(sizeof($res3) == 0){
            $message .= 'No Reminder for 1 days(Final);';
        }
        $response = [
            'message' => $message,
            'response_first_reminder' => $res1,
            'response_second_reminder' => $res2,
            'response_final_reminder' => $res3,
        ];
        echo json_encode($response);
    }

    public function SendReminder($n_days)
    {       
        $time = strtotime(date("Y-m-d").'');
        $nowPlusNDays = date("Y-m-d", strtotime("+".$n_days." day", $time));
        $remider_count = '';
        switch ($n_days) {
            case 7:
                $remider_count = 'first_reminder';
                break;
            
            case 3:
                $remider_count = 'second_reminder';
                break;
            
            default:
                $remider_count = 'final_reminder';
                break;
        }
        $rmd_subscriptions = Subscription::whereNotNull('subscribed_start')->whereRaw("DATE(subscribed_end) = '$nowPlusNDays'")->where($remider_count,0)->get(); 
        $arr_rmd_subscriptions = $rmd_subscriptions->toArray();
        $response_arr = array();
        foreach ($arr_rmd_subscriptions as $subscription) {
            $subscription = json_decode(json_encode($subscription));
            $response = $this->AddSequence($subscription->num_of_months, $subscription->client_id);
            array_push($response_arr, $response);
            if ($n_days == 14) {
                // create invoice
                $success = $this->CreateBilling($subscription);
                if ($success) {   
                    $this->SendEmail($subscription);
                } else {
                    array_push($response_arr, ['Create Billing Failed']);
                }
            } else {
                $this->SendEmail($subscription);
            }
        }
        Subscription::whereNotNull('subscribed_start')->whereRaw("DATE(subscribed_end) = '$nowPlusNDays'")->where($remider_count,0)->update([$remider_count => 1]);
        return $response_arr;
    }

    public function BeautymailSetting()
    {
        return [

            // These CSS rules will be applied after the regular template CSS
        
                'css' => '',
        
            'colors' => [
        
                'highlight' => '#f1f0eb',
                'button'    => '#ffe17f',
        
            ],
            'logo'        => [
                'path'   => 'https://projectmultatuli.org/wp-content/uploads/2023/11/LOGO-dengan-slogan2-e1700032263423.png',// Use %PUBLIC% for live site
                'width'  => '300',
                'height' => '100',
            ],
            'view' => [
                'senderName'  => 'Billing - Project Multatuli',
                'reminder'    => null,
                'unsubscribe' => null,
                'address'     => 'Jl. Raya Kebayoran Lama No. 18CD, Jakarta Selatan, DKI Jakarta',
        
                'logo'        => [
                    'path'   => 'https://projectmultatuli.org/wp-content/uploads/2023/11/LOGO-dengan-slogan2-e1700032263423.png',// Use %PUBLIC% for live site
                    'width'  => '300',
                    'height' => '100',
                ],
        
                'twitter'  => 'https://twitter.com/projectm_org',
                'facebook' => 'https://www.facebook.com/projectmultatuli/',
                'flickr'   => null,
            ],
        
        ];
    }

    public function SendEmail($subscription = null)
    {
        if ($subscription) {
            $invoice = Invoice::where('invoice_id', $subscription->latest_invoice_id)->first();
            $client = Client::where('client_id', $subscription->client_id)->first();
            $mail = new Beautymail($this->BeautymailSetting());

            $invoice = Invoice::where('invoice_id', $subscription->latest_invoice_id)->first();
            $invoiceAmount = InvoiceAmount::where('invoice_id', $subscription->latest_invoice_id)->first();
            $client = Client::where('client_id', $subscription->client_id)->first();
            $items = InvoiceItems::where('invoice_id', $subscription->latest_invoice_id)->get();
    
            $data = [
                "subscription" => $subscription,
                "invoice" => $invoice,
                "invoice_amount" => $invoiceAmount,
                "client" => $client,
                "items" => $items,
                "show_item_discounts" => false,
            ];
            $pdf = PDF::loadView('pdf.invoice', $data);
            $mail->send('mail.invoiceunpaid', $data, function($message) use ($client, $invoice, $pdf)
            {
                $mail_to_email = $client->client_email;
                $mail_to_name = $client->client_name;
                $message
                    ->from(env('MAIL_FROM_ADDRESS','admin@billing.projectmultatuli.org'), 'Billing - Project Multatuli')
                    ->to($mail_to_email, $mail_to_name)
                    ->subject('Invoice')
                    ->attachData($pdf->output(), "invoice".$invoice->invoice_number.".pdf");
            });
        }
    }

    public function CreateBilling($subscription = null)
    {
        $success = false;
        if ($subscription) {
            $amount = 30000;      
            $item_name = 'Tier I';
            switch ($subscription->client_group_id) {
                case '2':
                    $amount = 100000;
                    $item_name = 'Tier II';
                    break;
                case '3':
                    $amount = 250000;
                    $item_name = 'Tier III';
                    break;        
                default:
                    $amount = 30000;
                    $item_name = 'Tier I';
                    break;
            }
            $invoice_total = floatval($subscription->num_of_months) * floatval($amount);
            $invoice_id = $this->CreateInvoice($subscription->client_id, $invoice_total, $item_name); // create invoice
            if (!$invoice_id) {
                echo json_encode(['status' => 'Failed', 'message' => 'Cannot create invoice']);
            }
            // $payment_id = $this->CreatePayment($invoice_id, $invoice_total); // create payment
            // if (!$payment_id) {
            //     echo json_encode(['status' => 'Failed', 'message' => 'Cannot create payment']);
            // }
            // $responsexnd = $this->CreateInvoiceXendit($payment_id); //create xendit invoice
            // if ($responsexnd['success'] == false) {
            //     echo json_encode(['status' => 'Failed', 'message' => $responsexnd['message']]);
            // } else {
                InvoiceRecurring::where('invoice_id', $subscription->latest_invoice_id)->update(['invoice_id'=> $invoice_id]);
                Subscription::where('id', $subscription->id)->update(['latest_invoice_id' => $invoice_id]);
                $success = true;
            // }
        }
        return $success;
    }

    public function CreateInvoice($client_id, $invoice_total, $item_name)
    {        
        date_default_timezone_set('Asia/Jakarta');
        $datenow = date('Y-m-d');
        $timenow = date('H:i:s');
        $datetimenow = date('Y-m-d H:i:s');
        $invoicenumber = 'BIL'.date('Ymd').time();
        $date = date('Y-m-d');
        $invoice_date_due = date('Y-m-d', strtotime($date. ' + 14 days'));
        $invoice_url_key = Str::random(32);
        $invoice_data=array(
            'user_id'=>'2',
            'client_id'=>$client_id,
            'invoice_group_id'=>'5',
            'invoice_status_id'=>'2',
            'is_read_only'=>'0',
            'invoice_date_created'=>$datenow,
            'invoice_date_modified'=>$datetimenow,
            'invoice_time_created'=>$timenow,
            'invoice_date_due'=>$invoice_date_due,
            'invoice_number'=>$invoicenumber,
            'invoice_discount_amount'=>'0',
            'invoice_discount_percent'=>'0',
            'invoice_terms'=>'',
            'invoice_url_key'=>$invoice_url_key,
            'payment_method'=>'5',
        );
        $invoice_id = Invoice::insertGetId($invoice_data);
        $invoice_item_data=array(
            'invoice_id'=>$invoice_id,
            'item_tax_rate_id'=>'0',
            'item_date_added'=>$datetimenow,
            'item_name'=>$item_name,
            'item_quantity'=>'1',
            'item_price'=>$invoice_total,
            'item_order'=>'1',
        );
        $invoice_item_id = InvoiceItems::insertGetId($invoice_item_data);
        $invoice_item_amount_data=array(
            'item_id'=>$invoice_item_id,
            'item_subtotal'=>$invoice_total,
            'item_tax_total'=>'0',
            'item_discount'=>'0',
            'item_total'=>$invoice_total,
        );
        InvoiceItemAmounts::insert($invoice_item_amount_data);
        $invoice_amount_data=array(
            'invoice_id'=>$invoice_id,
            'invoice_sign'=>'1',
            'invoice_item_subtotal'=>$invoice_total,
            'invoice_item_tax_total'=>'0',
            'invoice_tax_total'=>'0',
            'invoice_total'=>$invoice_total,
            'invoice_paid'=>'0',
            'invoice_balance'=>'0',
        );
        InvoiceAmount::insert($invoice_amount_data);
        return $invoice_id;
    }

    public function CreatePayment($invoice_id, $invoice_total)
    {
        $datenow = date('Y-m-d');
        $payment_data=array(
            'invoice_id'=>$invoice_id,
            'payment_method_id'=>'5',
            'payment_date'=>$datenow,
            'payment_amount'=>$invoice_total,
            'payment_note'=>'THIS PAYMENT IS CREATED BY BILLING API',
        );
        $payment_id = Payment::insertGetId($payment_data);
        return $payment_id;
    }
    
    public function CreateInvoiceXendit($payment_id) {
        $success = true;
        $message = '';
        $data = array();
        $payment = Payment::where('payment_id', $payment_id)->first();
        if ($payment->payment_method_id == 5 && $payment->payment_xendit_id == '') {     
            $invoice = Invoice::where('invoice_id', $payment->invoice_id)->first();  
            $client = Client::where('client_id', $invoice->client_id)->first();  
            $setting_xnd_success_redirect_url = Setting::where('setting_key', 'xnd_success_redirect_url_api')->first();
            $setting_xnd_failure_redirect_url = Setting::where('setting_key', 'xnd_failure_redirect_url_api')->first();
            $success_redirect_url = $setting_xnd_success_redirect_url->setting_value;
            $failure_redirect_url = $setting_xnd_failure_redirect_url->setting_value;
            $payment_xendit_external_id = 'invoice_'.uniqid();

            $client_mobile = substr_replace($client->client_mobile, "+62", 0, 1);
            $address_line_2 = $client->client_address_2;
            if ($address_line_2 == '') {
                $address_line_2 = $client->client_address_1;
            }
            $params = [ 
                'external_id' => $payment_xendit_external_id,
                'amount' => $payment->payment_amount,
                'description' => 'Payment of Invoice Number '.$invoice->invoice_number.' for Billing Kawan M ',
                'invoice_duration' => 86400,
                'customer' => [
                    'given_names' => $client->client_name,
                    'surname' => $client->client_surname,
                    'email' => $client->client_email,
                    'mobile_number' => $client_mobile,
                    'addresses' => [
                        [
                            'city' => $client->client_city,
                            'country' => 'Indonesia',
                            'postal_code' => $client->client_zip,
                            'state' => $client->client_state,
                            'street_line1' => $client->client_address_1,
                            'street_line2' => $address_line_2
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
              
            $createInvoice = \Xendit\Invoice::create($params);
            if ($createInvoice['id']){
                $payment_xendit_id = $createInvoice['id'];
                $payment_xendit_url = $createInvoice['invoice_url'];
                $payment_xendit_status = $createInvoice['status'];
                $payment_xendit_expired = $createInvoice['expiry_date'];
                $payment_xendit_expired = date('Y-m-d H:i:s', strtotime($payment_xendit_expired));
                $data = array(
                        'payment_xendit_external_id' => $payment_xendit_external_id,
                        'payment_xendit_id' => $payment_xendit_id,
                        'payment_xendit_url' => $payment_xendit_url,
                        'payment_xendit_status' => $payment_xendit_status,
                        'payment_xendit_expired' => $payment_xendit_expired,
                    );
                Payment::where('payment_id', $payment_id)->update($data);

                Invoice::where('invoice_id', $payment->invoice_id)->update([
                        'invoice_status_id' => 10
                    ]);
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
