<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashierSession extends Model
{
    protected $fillable = [
        'cashier_id',
        'pos_id',
        'opened_at',
        'closed_at',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'notes',
        'status'
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'difference' => 'decimal:2'
    ];

    public function cashier()
    {
        return $this->belongsTo(Cashier::class);
    }

    public function pos()
    {
        return $this->belongsTo(Pos::class);
    }

    public function transactions()
    {
        return $this->hasMany(CashierTransaction::class);
    }

    public function counts()
    {
        return $this->hasMany(CashierCount::class);
    }

    public function isOpen()
    {
        return $this->status === 'ouverte';
    }

    public function isClosed()
    {
        return $this->status === 'fermee';
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'ouverte');
    }
}