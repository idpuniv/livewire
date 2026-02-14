<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PaymentCompleted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public Payment $payment;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
        Log::info("PaymentCompleted event created", ['payment_id' => $payment->id]);
    }

    /**
     * The channel the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        Log::info("PaymentCompleted broadcast on 'payments' channel");
        return new Channel('payments');
    }

    /**
     * The event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'payment.completed';
    }

    /**
     * Data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Récupère tous les produits de la commande
        $products = $this->payment->order->items->map(function ($item) {
            return [
                'id'    => $item->product->id,
                'name'  => $item->product->name,
                'price' => $item->product->price,
                'stock' => $item->product->stock,
                'image' => $item->product->image,
            ];
        });

        Log::info("Broadcasting products for PaymentCompleted", ['products' => $products->toArray()]);

        return [
            'products' => $products,
        ];
    }
}
