<?php

namespace App\Listeners;

use App\Events\OrderPayed;
use App\Models\OrderItem;
use App\Models\Product;
use App\Enums\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DecrementStock
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPayed $event): void
    {
        $order = $event->order;
        // Log::info('order id', $order->id);

        if ($order->status === Status::PAID || $order->status === Status::CONFIRMED) {
            $orderItems = OrderItem::where('order_id', $order->id)
                ->get(['product_id', 'quantity']);

            foreach ($orderItems as $item) {
                Product::where('id', $item->product_id)
                    ->update(['stock' => DB::raw("stock - {$item->quantity}")]);
            }
        }
    }
}
