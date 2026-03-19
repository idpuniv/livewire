<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Events\ProductUpdated;
use App\Traits\HasImage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{

    use HasFactory;
    use HasImage;
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
        'tva_rate',
        'stock_threshold',
    ];


    protected $casts = [
        'published_at' => 'datetime',
        'price' => 'decimal:2',
        'tva_rate' => 'decimal:2',
        'stock_threshold' => 'integer',
    ];


    protected static function booted()
    {
        static::updated(function ($product) {
            broadcast(new ProductUpdated($product))->toOthers();
        });
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Vérifie si le stock est en dessous du seuil
     */
    public function isBelowThreshold(): bool
    {
        return $this->stock <= $this->stock_threshold;
    }

    /**
     * Vérifie si le stock est critique (en dessous de la moitié du seuil)
     */
    public function isCritical(): bool
    {
        return $this->stock <= ($this->stock_threshold / 2);
    }
}
