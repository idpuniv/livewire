<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Events\ProductUpdated;
use Illuminate\Support\Facades\Log;

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
        'barcode',
        'image',
    ];

    protected static function booted()
    {
        static::updated(function ($product) {
            // Log::info('broadcasted from model');
            broadcast(new ProductUpdated($product))->toOthers();
        });
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
