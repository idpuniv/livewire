<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Champs autorisés pour l'insertion / mise à jour en masse
    protected $fillable = [
        'name',
        'code',
        'description',
        'price',
        'stock',
        'published_at',
        'barcode'
    ];

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
