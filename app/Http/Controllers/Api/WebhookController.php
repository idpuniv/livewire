<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $transaction = Transaction::where('reference', $request->reference)->firstOrFail();
        $payment = $transaction->payment;
        $checkout = $payment->checkout;
        $invoice = $checkout->invoice;

        // 6. Mise à jour statuts
        $transaction->update(['status' => 'success']);

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $checkout->update(['status' => 'completed']);

        $invoice->update(['status' => 'paid']);

        // 7. Receipt
        Receipt::create([
            'payment_id' => $payment->id,
            'reference' => 'RCPT-' . time(),
        ]);

        return response()->json(['ok' => true]);
    }
}

