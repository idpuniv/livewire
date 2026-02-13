<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $action; // 'created', 'updated', 'deleted', 'stock_changed'
    public ?array $product; // Données du produit concerné
    public ?int $productId; // ID si supprimé

    public function __construct(string $action, $product = null, ?int $productId = null)
    {
        $this->action = $action;

        if ($product) {
            $this->product = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $product->price,
                'stock' => $product->stock,
                'image' => $product->image,
            ];
        }

        $this->productId = $productId ?? ($product?->id);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('products'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.updated';
    }
}
