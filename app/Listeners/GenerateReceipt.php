<?php

namespace App\Listeners;

use App\Models\Receipt;
use App\Events\PaymentCompleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateReceipt implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentCompleted $event)
    {
        $payment = $event->payment;

        try {
            $receipt = Receipt::create([
                'order_id' => $payment->order_id,
                'casher_id' => Auth::id(), // utilisateur connecté
                'amount' => $payment->amount,
                'payment_method' => $payment->method,
                'payment_id'  => $payment->id,
                'paid_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Receipt creation failed: ' . $e->getMessage());
            return; // arrêt si reçu critique
        }

        $printToken = (string) Str::uuid();
        session()->put('current_print_token', $printToken);

        $printData = [
            'receipt_number' => $receipt->receipt_number,
            'amount' => $receipt->amount,
            'method' => $receipt->payment_method,
            'date' => $receipt->paid_at->format('Y-m-d H:i'),
            'callback_url' => route('printer.webhook'), // webhook pour le statut
            'print_token' => $printToken,
        ];

        try {
            Http::post('http://localhost:8080/print', $printData);
            Log::info('Receipt sent to printer: ' . $receipt->receipt_number . ' (token: '.$printToken.')');
        } catch (\Throwable $e) {
            Log::error('Print failed: ' . $e->getMessage());
            $receipt->update([
                'print_status' => 'failed',
                'print_message' => $e->getMessage(),
            ]);
        }
    }
}