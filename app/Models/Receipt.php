<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = ['order_id', 'amount', 'payment_method'];

    // Un reçu appartient à une commande
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

