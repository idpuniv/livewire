<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employments'; // Utilise la même table
    
    protected $fillable = [
        'people_id',
        'position_id',
        'start_date',
        'salary',
        'department'
    ];

    protected static function booted()
    {
        static::addGlobalScope('current', function ($builder) {
            $builder->where('is_current', true);
        });
    }

    public function people()
    {
        return $this->belongsTo(Person::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}