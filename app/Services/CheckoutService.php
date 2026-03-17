<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;

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
    public function createOrderAndPay(Cart $cart, float $amountPaid, string $paymentMethod, array $customer): array
    {
        if ($paymentMethod === 'cash' && $amountPaid < $cart->total) {
            return [
                'success' => false,
                'message' => 'Montant insuffisant.',
                'status' => 'error'
            ];
        }

        try {
            return DB::transaction(function () use ($cart, $amountPaid, $paymentMethod, $customer) {
                $order = $this->orderService->createOrderFromCart($cart, $customer);
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
                'message' => 'Erreur IDO: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
}