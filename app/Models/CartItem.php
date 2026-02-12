<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'quantity', 'price'];

    // Chaque item appartient à un panier
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    // Chaque item correspond à un produit
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

