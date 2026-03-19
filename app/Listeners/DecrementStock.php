<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Events\StockLowEvent;
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
                // Récupère tous les produits concernés
                $ids = array_keys($orderItems);
                $products = Product::whereIn('id', $ids)->get();

                foreach ($products as $product) {
                    $quantity = $orderItems[$product->id] ?? 0;

                    if ($product->stock >= $quantity) {
                        // Sauvegarder l'ancien stock pour référence
                        $oldStock = $product->stock;
                        
                        // Décrément SQL unique
                        Product::where('id', $product->id)
                            ->update(['stock' => DB::raw("stock - {$quantity}")]);
                        
                        // Recharger le produit pour avoir le nouveau stock
                        $product->refresh();
                        
                        // Logs détaillés
                        Log::info("Stock décrémenté pour {$product->name}", [
                            'product_id' => $product->id,
                            'ancien_stock' => $oldStock,
                            'quantite_vendue' => $quantity,
                            'nouveau_stock' => $product->stock,
                            'seuil' => $product->stock_threshold
                        ]);
                        
                        // Vérifier les alertes de stock en utilisant les helpers du modèle
                        $this->checkStockAlerts($product, $oldStock, $quantity);
                        
                    } else {
                        Log::warning("Stock insuffisant pour le produit {$product->id}", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'stock_actuel' => $product->stock,
                            'quantite_demandee' => $quantity
                        ]);
                        
                        // Optionnel : lancer une exception ou annuler la transaction
                        // throw new \Exception("Stock insuffisant pour {$product->name}");
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error("Erreur lors de la décrémentation du stock : " . $e->getMessage());
        }
    }
    
    /**
     * Vérifie les alertes de stock en utilisant les helpers du modèle Product
     */
    private function checkStockAlerts($product, $oldStock, $soldQuantity)
    {
        // Utilisation des helpers du modèle Product
        
        // 1. Vérifier si le stock est critique (moitié du seuil)
        if ($product->isCritical()) {
            event(new StockLowEvent($product, $oldStock, $soldQuantity));
            
            Log::critical("STOCK CRITIQUE pour {$product->name} !", [
                'product_id' => $product->id,
                'stock' => $product->stock,
                'seuil' => $product->stock_threshold,
                'ratio' => $product->stock . '/' . $product->stock_threshold
            ]);
        }
        // 2. Vérifier si le stock est sous le seuil (mais pas critique)
        elseif ($product->isBelowThreshold()) {
            event(new StockLowEvent($product, $oldStock, $soldQuantity));
            
            Log::warning("Stock faible pour {$product->name}", [
                'product_id' => $product->id,
                'stock' => $product->stock,
                'seuil' => $product->stock_threshold
            ]);
        }
        
        // 3. Vérifier si le stock est revenu au-dessus du seuil (résolution)
        if ($oldStock <= $product->stock_threshold && $product->stock > $product->stock_threshold) {
            Log::info("Stock revenu à un niveau normal pour {$product->name}", [
                'product_id' => $product->id,
                'stock' => $product->stock,
                'seuil' => $product->stock_threshold
            ]);
            
            // Optionnel : événement de résolution
            // event(new StockNormalEvent($product));
        }
    }
}