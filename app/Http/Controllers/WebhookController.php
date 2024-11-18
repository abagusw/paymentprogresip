<?php

namespace App\Http\Controllers;

use App\Models\Invoices;
use App\Models\Settings;

class WebhookController extends Controller
{
    public function VerifyWebhookToken()
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

    public function CallbackInvoice()
    {
        $verified = $this->VerifyWebhookToken();
        if ($verified) {
            $data = request()->all();

            $status = $data['status'];
            $external_id = $data['external_id'];

            Invoices::where('xendit_invoice_id', $external_id)->update([
                'xendit_invoice_status' => $status
            ]);
            $invoice = Invoices::where('xendit_invoice_id', $external_id)->first();
            if (!$invoice) {
                return response()->json(['success'=>false, 'message'=>'Invoice reference id not found']);
            }
            return response()->json(['success'=>true, 'message'=>'Update invoice success', 'data' => $data]);
        } else {
            return response()->json(['success'=>false, 'message'=>'Token not match']);
        }
    }
}
