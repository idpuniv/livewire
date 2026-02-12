<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; 

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'transaction_type',
        'amount',
        'status',
        'gateway_reference',
        'gateway_response',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'metadata' => 'array'
    ];

    // Constantes pour les types de transaction
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_ADJUSTMENT = 'adjustment';

    // Constantes pour les statuts
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';


    protected static function booted()
{
    static::creating(function ($transaction) {

        if (empty($transaction->reference)) {
            $transaction->reference = self::generateReference(
                $transaction->transaction_type
            );
        }
    });
}


    // Relations
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    // Scopes
    public function scopePayments($query)
    {
        return $query->where('transaction_type', self::TYPE_PAYMENT);
    }

    public function scopeRefunds($query)
    {
        return $query->where('transaction_type', self::TYPE_REFUND);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // Méthodes utilitaires
    public function markAsSuccess($gatewayReference = null, $gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_SUCCESS,
            'gateway_reference' => $gatewayReference,
            'gateway_response' => $gatewayResponse
        ]);
    }

    public function markAsFailed($gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_response' => $gatewayResponse
        ]);
    }

    public function isSuccessful()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function getFormattedAmountAttribute()
    {
        $prefix = $this->transaction_type === self::TYPE_REFUND ? '-' : '';
        return $prefix . number_format($this->amount, 2, ',', ' ') . ' €';
    }

    public static function generateReference(string $type): string
{
    $prefix = match ($type) {
        self::TYPE_PAYMENT => 'PAY',
        self::TYPE_REFUND => 'REF',
        self::TYPE_ADJUSTMENT => 'ADJ',
        default => 'TRX',
    };

    return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(10));
}

}