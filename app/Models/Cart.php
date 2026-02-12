<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'status'];

    // Un panier a plusieurs items
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    // Un panier peut générer une facture
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    // ---------------------------
    // Méthodes de calcul
    // ---------------------------

    // Sous-total du panier
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    // Taxe (20%)
    public function getTaxAttribute()
    {
        return $this->subtotal * 0.20;
    }

    // Total TTC
    public function getTotalAttribute()
    {
        return $this->subtotal + $this->tax;
    }
}
