<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Checkout;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Enums\Status;

class OrderService
{
    public function createOrderFromCart(Cart $cart): Order
    {
        return DB::transaction(function () use ($cart) {
            $subtotal = $cart->subtotal;
            $tax = $cart->tax;
            $total = $cart->total;

            $checkout = Checkout::create([
                'cart_id' => $cart->id,
                'user_id' => Auth::id() ?? 1,
                'amount' => $total,
                'status' => 'pending'
            ]);

            // 1. Créer la commande
            $order = Order::create([
                'checkout_id' => $checkout->id,
                'status' => 'pending',
                'amount_paid' => 0,
            ]);

            // 2. Créer les items de commande (SANS invoice)
            $this->createOrderItems($order, $cart);

            // 3. Créer la facture APRÈS les items (pour avoir les totaux)
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'checkout_id' => $checkout->id,
                'subtotal' => $order->subtotal, // Utiliser les valeurs de la commande
                'tax' => $order->tax,
                'total' => $order->total,
                'status' => 'pending'
            ]);

            // 4. Créer les items de facture à partir des order_items
            $this->createInvoiceItems($invoice, $order);

            // 5. Mettre à jour la commande avec l'invoice_id
            $order->update(['invoice_id' => $invoice->id]);

            return $order;
        });
    }

    private function createOrderItems(Order $order, Cart $cart): void
    {
        $orderItemsData = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $quantity = $item->quantity;
            $unitPrice = $item->price;
            $totalHt = $quantity * $unitPrice;
            $tvaRate = $product->tva_rate ?? 0;
            $taxAmount = $totalHt * $tvaRate / 100;
            $totalTtc = $totalHt + $taxAmount;

            $orderItemsData[] = [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_ht' => $totalHt,
                'tax_rate' => $tvaRate,
                'tax_amount' => $taxAmount,
                'total_ttc' => $totalTtc,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        if (!empty($orderItemsData)) {
            OrderItem::insert($orderItemsData);
        }
    }

    private function createInvoiceItems(Invoice $invoice, Order $order): void
    {
        $invoiceItemsData = [];

        foreach ($order->items as $item) {
            $invoiceItemsData[] = [
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_code' => $item->product_code,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'total_ht' => $item->total_ht,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'total_ttc' => $item->total_ttc,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        if (!empty($invoiceItemsData)) {
            InvoiceItem::insert($invoiceItemsData);
        }
    }

    public function updateOrderWithExistingCart(Order $order, array $product, int $productId): void
    {
        $tvaRate = $product['tva_rate'] ?? 0;
        
        $item = OrderItem::firstOrCreate(
            [
                'order_id' => $order->id,
                'product_id' => $productId
            ],
            [
                'product_name' => $product['name'],
                'product_code' => $product['code'],
                'unit_price' => $product['price'],
                'quantity' => 0,
                'total_ht' => 0,
                'tax_rate' => $tvaRate,
                'tax_amount' => 0,
                'total_ttc' => 0,
            ]
        );

        $item->quantity += 1;
        $item->total_ht = $item->quantity * $item->unit_price;
        $item->tax_amount = $item->total_ht * ($item->tax_rate / 100);
        $item->total_ttc = $item->total_ht + $item->tax_amount;
        $item->save();

        if ($order->invoice) {
            $invoiceItem = InvoiceItem::firstOrCreate(
                [
                    'invoice_id' => $order->invoice->id,
                    'product_id' => $productId
                ],
                [
                    'product_name' => $product['name'],
                    'product_code' => $product['code'],
                    'unit_price' => $product['price'],
                    'quantity' => 0,
                    'total_ht' => 0,
                    'tax_rate' => $tvaRate,
                    'tax_amount' => 0,
                    'total_ttc' => 0,
                ]
            );

            // Synchroniser avec l'order_item
            $invoiceItem->quantity = $item->quantity;
            $invoiceItem->total_ht = $item->total_ht;
            $invoiceItem->tax_amount = $item->tax_amount;
            $invoiceItem->total_ttc = $item->total_ttc;
            $invoiceItem->save();
        }
    }
}