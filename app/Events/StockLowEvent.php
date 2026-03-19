<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLowEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $product;
    public $oldStock;
    public $soldQuantity;

    public function __construct(Product $product, $oldStock, $soldQuantity)
    {
        $this->product = $product;
        $this->oldStock = $oldStock;
        $this->soldQuantity = $soldQuantity;
    }

    public function broadcastOn()
    {
        return new Channel('stock-alerts');
    }

    public function broadcastAs()
    {
        return 'stock.low';
    }

    public function broadcastWith()
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'stock' => $this->product->stock,
            'threshold' => $this->product->stock_threshold,
            'old_stock' => $this->oldStock,
            'sold_quantity' => $this->soldQuantity,
            'message' => "Stock faible : {$this->product->name} ({$this->product->stock} / seuil: {$this->product->stock_threshold})",
            'level' => $this->product->isCritical() ? 'critical' : 'warning'
        ];
    }
}
