<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\Checkout;
use App\Models\OrderItem;
use App\Models\InvoiceItem;
use App\Enums\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Events\PaymentCompleted;

class CheckoutService
{
    protected PaymentService $paymentService;
    protected OrderService $orderService;

    public function __construct(
        PaymentService $paymentService,
        OrderService $orderService
    ) {
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
    }

    /**
     * Crée une commande ET effectue le paiement en une seule transaction
     */
    public function createOrderAndPay(Cart $cart, float $amountPaid, string $paymentMethod): array
    {
        // Vérification du montant
        if ($paymentMethod === 'cash' && $amountPaid < $cart->total) {
            return [
                'success' => false,
                'message' => 'Montant insuffisant.',
                'status' => 'error'
            ];
        }

        try {
            return DB::transaction(function () use ($cart, $amountPaid, $paymentMethod) {
                // 1. Créer la commande
                $order = $this->orderService->createOrderFromCart($cart);
                
                // 2. Payer la commande
                $paymentResult = $this->paymentService->processPayment(
                    $order,
                    $amountPaid,
                    $paymentMethod
                );

                if (!$paymentResult['success']) {
                    throw new \Exception($paymentResult['message']);
                }

                return [
                    'success' => true,
                    'message' => 'Commande créée et payée avec succès.',
                    'order' => $order,
                    'payment' => $paymentResult['payment']
                ];
            });
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
}