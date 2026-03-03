<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $guarded = ['id'];
    
    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2'
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Ces accesseurs ne sont plus nécessaires si les champs sont en base,
     * mais on peut les garder comme fallback ou pour compatibilité
     */
    public function getSubtotalAttribute($value)
    {
        // Si la valeur vient de la base, on l'utilise
        if (!is_null($value)) {
            return $value;
        }
        
        // Fallback : calcul à la volée
        return $this->items->sum(function ($item) {
            return $item->total_ht ?? ($item->quantity * $item->unit_price);
        });
    }

    public function getTaxAttribute($value)
    {
        if (!is_null($value)) {
            return $value;
        }
        
        return $this->items->sum('tax_amount');
    }

    public function getTotalAttribute($value)
    {
        if (!is_null($value)) {
            return $value;
        }
        
        return $this->subtotal + $this->tax;
    }
}