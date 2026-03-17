<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Receipt extends Model
{
    protected $fillable = [
        'order_id',
        'payment_id',
        'casher_id',
        'amount',
        'payment_method',
        'receipt_number',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    /**
     * Boot model (auto génération du receipt_number)
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (!$receipt->receipt_number) {
                $receipt->receipt_number = self::generateReceiptNumber();
            }

            if (!$receipt->paid_at) {
                $receipt->paid_at = now();
            }

            // fallback si payment_method manquant
            if (!$receipt->payment_method) {
                $receipt->payment_method = 'cash';
            }
        });
    }

    /**
     * Générer un numéro de reçu unique
     */
    public static function generateReceiptNumber(): string
    {
        return 'RCPT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
    }

    /**
     * Relation : un reçu appartient à une commande
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Accéder au client via la commande
     */
    public function customer()
    {
        return $this->order?->customer();
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Format du montant (helper)
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2, ',', ' ') . ' FCFA';
    }
}
