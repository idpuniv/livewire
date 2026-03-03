<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_code',
        'unit_price',
        'quantity',
        'total_ht', 
        'tax_rate',
        'tax_amount',
        'total_ttc'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_ht' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Boot pour calculs automatiques si nécessaire
    protected static function booted()
    {
        static::creating(function ($item) {
            $item->total_ht = $item->quantity * $item->unit_price;
            $item->tax_amount = $item->total_ht * ($item->tax_rate / 100);
            $item->total_ttc = $item->total_ht + $item->tax_amount;
        });

        static::updating(function ($item) {
            $item->total_ht = $item->quantity * $item->unit_price;
            $item->tax_amount = $item->total_ht * ($item->tax_rate / 100);
            $item->total_ttc = $item->total_ht + $item->tax_amount;
        });
    }

    // Accesseur pour compatibilité (si ancien code utilise total_price)
    public function getTotalPriceAttribute()
    {
        return $this->total_ttc;
    }
}