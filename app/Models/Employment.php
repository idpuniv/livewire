<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employment extends Model
{
    protected $fillable = [
        'people_id',
        'position_id',
        'start_date',
        'end_date',
        'salary',
        'department',
        'reason_left',
        'is_current'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function people()
    {
        return $this->belongsTo(Person::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    // Scope pour les employés actuels
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // Scope pour une période donnée
    public function scopeDuring($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function($q) use ($date) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $date);
            });
    }
}