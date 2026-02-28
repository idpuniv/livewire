<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cart; // Importer le bon modèle
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Enums\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Events\PaymentCompleted;

class PaymentService
{
    /**
     * Traiter le paiement d'une commande existante
     */
    public function processPayment(Order $order, float $amountPaid, string $paymentMethod): array
    {
        $total = $order->invoice->total;

        if ($paymentMethod === 'cash' && $amountPaid < $total) {
            return [
                'success' => false,
                'message' => 'Montant insuffisant.',
                'status' => 'error'
            ];
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'order_id'  => $order->id,    
                'user_id'   => Auth::id() ?? 1,
                'method'    => $paymentMethod,
                'amount'    => $total,
                'status'    => Status::SUCCESS,
            ]);

            Transaction::create([
                'payment_id'       => $payment->id,
                'transaction_type' => 'payment',
                'amount'           => $total,
                'status'           => Status::SUCCESS,
            ]);

            $order->update([
                'status'       => Status::CONFIRMED,
                'amount_paid'  => $total,
            ]);

            $order->invoice->update([
                'status' => Status::PAID,
            ]);

            DB::commit();
            
            event(new PaymentCompleted($payment));

            return [
                'success' => true,
                'message' => 'Paiement effectué avec succès.',
                'status' => 'success',
                'payment' => $payment
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return [
                'success' => false,
                'message' => 'Erreur lors du paiement.',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Vérifier si le paiement est possible
     */
    public function canPay(?Order $order, ?Cart $cart, float $amountPaid, string $paymentMethod): bool
    {
        // Si pas de commande ET pas de panier
        if (!$order && !$cart) {
            return false;
        }

        // Si panier vide
        if ($cart && $cart->items()->count() === 0) {
            return false;
        }

        // Si commande sans invoice
        if ($order && !$order->invoice) {
            return false;
        }

        // Calculer le total
        $total = $order?->invoice?->total ?? $cart?->total ?? 0;

        // Si total = 0
        if ($total <= 0) {
            return false;
        }

        // Vérifier le montant pour paiement en espèces
        if ($paymentMethod === 'cash') {
            return $amountPaid >= $total;
        }

        return true;
    }

    /**
     * Calculer la monnaie à rendre
     */
    public function calculateChange(float $amountPaid, float $total): float
    {
        return max($amountPaid - $total, 0);
    }
}