<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; 

class Checkout extends Model
{
    protected $fillable = [
        'cart_id',
        'user_id',
        'amount',
        'tax',
        'total',
        'payment_method',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($checkout) {
            // Générer un identifiant unique simple
            $checkout->reference = 'CHK-' . date('Ymd') . '-' . Str::upper(Str::random(6));
        });
    }
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

