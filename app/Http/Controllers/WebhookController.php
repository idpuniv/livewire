<?php

namespace App\Http\Controllers;
use App\Events\ReceiptPrinted;
use App\Models\Receipt;

use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $printToken = $request->input('print_token');
        $status = $request->input('status');
        $message = $request->input('message');

        $receipt = Receipt::where('receipt_number', $request->input('receipt_number'))->first();

        if ($receipt) {
            $receipt->update([
                'print_status' => $status,
                'print_message' => $message,
            ]);
        }

        event(new ReceiptPrinted($receipt, $printToken));

        return response()->json(['received' => true]);
    }
}
