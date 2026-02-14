<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DecrementStock
{
    public function handle(PaymentCompleted $event)
    {

        Log::info("from decrement stock");
        $order = $event->payment->order;

        // Récupère tous les items de la commande
        $orderItems = $order->items->mapWithKeys(function ($item) {
            return [$item->product_id => $item->quantity];
        })->toArray(); // [product_id => quantity]

        if (empty($orderItems)) {
            return;
        }

        try {
            DB::transaction(function () use ($orderItems) {
                // Décrémente tous les produits en une seule requête par produit
                $ids = array_keys($orderItems);
                $products = Product::whereIn('id', $ids)->get();

                foreach ($products as $product) {
                    $quantity = $orderItems[$product->id] ?? 0;

                    if ($product->stock >= $quantity) {
                        // Décrément SQL unique
                        Product::where('id', $product->id)
                            ->update(['stock' => DB::raw("stock - {$quantity}")]);
                    } else {
                        Log::warning("Stock insuffisant pour le produit {$product->id}");
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error("Erreur lors de la décrémentation du stock : " . $e->getMessage());
        }
    }
}
