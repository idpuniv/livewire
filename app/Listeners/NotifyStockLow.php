<?php

namespace App\Listeners;

use App\Events\StockLowEvent;
use App\Notifications\StockLowNotification;
use App\Models\User;
use App\Roles\Roles;
use Illuminate\Support\Facades\Notification;
use App\Permissions\ProductPermissions;

class NotifyStockLow
{
    public function handle(StockLowEvent $event)
    {
        // 1. Notifier tous les gestionnaires de stock via le RÔLE
        $stockManagers = User::role(Roles::STOCK_MANAGER)->get();

        if ($stockManagers->isNotEmpty()) {
            Notification::send($stockManagers, new StockLowNotification($event->product));

            \Log::info("Notification stock faible envoyée aux gestionnaires", [
                'product' => $event->product->name,
                'stock' => $event->product->stock,
                'seuil' => $event->product->stock_threshold,
                'destinataires' => $stockManagers->count()
            ]);
        }

        // 2. Si stock critique, notifier aussi les admins
        if ($event->product->isCritical()) {
            $admins = User::role(Roles::ADMIN)->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new StockLowNotification($event->product, true));

                \Log::warning("Stock CRITIQUE - Notification admin", [
                    'product' => $event->product->name,
                    'stock' => $event->product->stock,
                    'seuil' => $event->product->stock_threshold
                ]);
            }
        }
    }
}
