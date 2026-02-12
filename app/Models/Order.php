<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'invoice_id',
        'status',
        'amount_paid',
        ];

    // Une commande appartient à une facture
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Une commande peut générer un reçu
    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // Méthode pour vérifier si l'ordre est payé
    public function isPaid()
    {
        return $this->payments()->where('status', 'completed')->exists();
    }

    // Méthode pour obtenir le montant total payé
    public function getTotalPaidAttribute()
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount');
    }

    // Méthode pour vérifier si un paiement est en attente
    public function hasPendingPayment()
    {
        return $this->payments()->where('status', 'pending')->exists();
    }

    // ---------------------------
    // Méthodes de calcul basées sur les items
    // ---------------------------

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Sous-total de la commande
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });
    }

    // Taxe (20%)
    public function getTaxAttribute()
    {
        return $this->subtotal * 0.20;
    }

    // Total TTC
    public function getTotalAttribute()
    {
        return $this->subtotal + $this->tax;
    }
}
