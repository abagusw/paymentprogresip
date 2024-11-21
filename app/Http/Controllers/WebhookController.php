<?php

namespace App\Http\Controllers;

use App\Models\Clients;
use App\Models\Invoices;
use App\Models\InvoicesRecurring;
use App\Models\Settings;

class WebhookController extends Controller
{
    public function VerifyWebhookToken(): bool
    {
        $xnd_webhook_token = Settings::where('setting_key', 'xnd_webhook_token')->first();
        $xenditXCallbackToken = $xnd_webhook_token->value_text;

        $reqHeaders = getallheaders();
        $xIncomingCallbackTokenHeader = isset($reqHeaders['X-Callback-Token']) ? $reqHeaders['X-Callback-Token'] : "";
        if ($xIncomingCallbackTokenHeader === "") {
            $xIncomingCallbackTokenHeader = isset($reqHeaders['X-CALLBACK-TOKEN']) ? $reqHeaders['X-CALLBACK-TOKEN'] : "";
        } 
        if ($xIncomingCallbackTokenHeader === "") {
            $xIncomingCallbackTokenHeader = isset($reqHeaders['x-callback-token']) ? $reqHeaders['x-callback-token'] : "";
        }

        if($xIncomingCallbackTokenHeader === $xenditXCallbackToken){
            return true;
        }
        return false;
    }

    public function UpdateClientSubscription($active, $client_id, $subscribed_start = null, $subscribed_end = null) {
        
        if ($active) {
            Clients::where('client_id', $client_id)->update(
                    [
                        'subscribed_start' => $subscribed_start,
                        'subscribed_end' => $subscribed_end,
                        'subscribed_status' => 1
                    ]
                );
        } else { 
            Clients::where('client_id', $client_id)->update(
                    [
                        'subscribed_status' => 0
                    ]
                );
        }
    }

    public function CallbackInvoice()
    {
        $verified = $this->VerifyWebhookToken();
        if ($verified) {
            $data = request()->all();

            $status = $data['status'];
            $external_id = $data['external_id'];

            $invoice = Invoices::where('external_invoice_id', $external_id)->first();
            if (!$invoice) {
                return response()->json(['success'=>false, 'message'=>'Invoice reference id not found']);
            }
            Invoices::where('external_invoice_id', $external_id)->update([
                'xendit_invoice_status' => $status
            ]);
            
            //update client subscription status
            if ($status == 'PAID') {
                $date_paid = strtotime($data['paid_at']);
                $date_end = strtotime("+" . $invoice->number_of_months . " month",  $date_paid);
                $subscribed_start = date('Y-m-d H:i:s', $date_paid);
                $subscribed_end = date('Y-m-d H:i:s', $date_end);
                $this->UpdateClientSubscription(true, $invoice->client_id, $subscribed_start, $subscribed_end);
            } else if ($status == 'EXPIRED') { 
                $this->UpdateClientSubscription(false, $invoice->client_id);
            }
            return response()->json(['success'=>true, 'message'=>'Update invoice success', 'data' => $data]);
        } else {
            return response()->json(['success'=>false, 'message'=>'Token not match']);
        }
    }
    
    public function CallbackInvoiceRecurring()
    {
        $verified = $this->VerifyWebhookToken();
        if ($verified) {
            $response = request()->all();

            $event = $response['event'];
            $data = $response['data'];
            $reference_id = $data['reference_id'];
            $status = $data['status'];

            // get invoice recurring
            $invoice_recurring = InvoicesRecurring::where('external_invoice_recurring_id', $reference_id)->first();
            if (!$invoice_recurring) {
                return response()->json(['status'=>'Invoice recurring plan reference id not found']);
            }

            // save recurring latest event and status
            InvoicesRecurring::where('external_invoice_recurring_id', $reference_id)->update(
                [
                    'xnd_recurring_latest_event'=>$event,
                    'xnd_recurring_plan_status'=>$status
                ]
            );
            
            switch ($event) {
                case 'recurring.plan.activated':
                    break;
                
                case 'recurring.plan.inactivated':          
                    $this->UpdateClientSubscription(false, $invoice_recurring->client_id);
                    break;    

                case 'recurring.cycle.retrying':    
                    break;

                case 'recurring.cycle.failed': 
                    if ($data['attempt_count'] == 2) {
                    }
                    break;    
                
                case 'recurring.cycle.created': // store the next payment, save the cycle id only
                    InvoicesRecurring::where('external_invoice_recurring_id', $reference_id)->update([
                        'xnd_recurring_cycle_id' => $data['id']
                    ]);
                    break;

                case 'recurring.cycle.succeeded':
                    $date_paid = strtotime($data['scheduled_timestamp']);
                    $date_end = strtotime("+" . $invoice_recurring->number_of_months . " month", $date_paid);
                    $subscribed_start = date('Y-m-d H:i:s', $date_paid);
                    $subscribed_end = date('Y-m-d H:i:s', $date_end);
                    $this->UpdateClientSubscription(true, $invoice_recurring->client_id, $subscribed_start, $subscribed_end);
                    break;
                
                default:
                    break;
            }

            return response()->json(['success'=>true, 'message'=>'Update invoice recurring success', 'data' => $data]);
        } else {
            return response()->json(['success'=>false, 'message'=>'Token not match']);
        }
    }
}
