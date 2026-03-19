<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StockLowNotification extends Notification
{
    use Queueable;

    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Stock faible : ' . $this->product->name)
            ->line('Le produit **' . $this->product->name . '** a un stock faible.')
            ->line('Stock actuel: ' . $this->product->stock)
            ->line('Seuil d\'alerte: ' . ($this->product->stock_threshold ?? 5))
            ->action('Voir le produit', url('/products/' . $this->product->id));
    }

    public function toDatabase($notifiable)
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'stock' => $this->product->stock,
            'threshold' => $this->product->stock_threshold ?? 5,
            'message' => 'Stock faible : ' . $this->product->name . ' (' . $this->product->stock . ')',
        ];
    }
}
