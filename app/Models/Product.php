<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Events\ProductUpdated;

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
        // À la création
        static::created(function ($product) {
            broadcast(new ProductUpdated('created', $product));
        });

        // À la mise à jour
        static::updated(function ($product) {
            // Vérifier si le stock a changé
            if ($product->wasChanged('stock')) {
                broadcast(new ProductUpdated('stock_changed', $product));
            } else {
                broadcast(new ProductUpdated('updated', $product));
            }
        });

        // À la suppression
        static::deleted(function ($product) {
            broadcast(new ProductUpdated('deleted', null, $product->id));
        });
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
