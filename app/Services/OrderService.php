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

            $invoice = Invoice::create([
                'checkout_id' => $checkout->id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending'
            ]);

            $order = Order::create([
                'checkout_id' => $checkout->id,
                'status' => 'pending',
                'amount_paid' => 0,
                'invoice_id' => $invoice->id
            ]);

            $this->createOrderItems($order, $invoice, $cart);
            

            return $order;
        });
    }

    private function createOrderItems(Order $order, Invoice $invoice, Cart $cart): void
    {
        $orderItemsData = [];
        $invoiceItemsData = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $productTotal = $item->quantity * $item->price;
            $productTax = $productTotal * 0.20;

            $orderItemsData[] = [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'unit_price' => $item->price,
                'quantity' => $item->quantity,
                'total_price' => $productTotal,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $invoiceItemsData[] = [
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'unit_price' => $item->price,
                'quantity' => $item->quantity,
                'total_price' => $productTotal,
                'tax_rate' => 20.00,
                'tax_amount' => $productTax,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        if (!empty($orderItemsData)) {
            OrderItem::insert($orderItemsData);
        }
        if (!empty($invoiceItemsData)) {
            InvoiceItem::insert($invoiceItemsData);
        }
    }

    public function updateOrderWithExistingCart(Order $order, array $product, int $productId): void
    {
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
                'total_price' => 0,
            ]
        );

        $item->quantity += 1;
        $item->total_price = $item->quantity * $item->unit_price;
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
                    'total_price' => 0,
                    'tax_rate' => 20.0,
                    'tax_amount' => 0,
                ]
            );

            $invoiceItem->quantity += 1;
            $invoiceItem->total_price = $invoiceItem->quantity * $invoiceItem->unit_price;
            $invoiceItem->tax_amount = $invoiceItem->total_price * ($invoiceItem->tax_rate / 100);
            $invoiceItem->save();
        }
    }
}