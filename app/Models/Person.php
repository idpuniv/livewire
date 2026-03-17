<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $fillable = [
        'name',
        'firstname',
        'phone',
        'email',
        'cnib'
    ];

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function supplier()
    {
        return $this->hasOne(Supplier::class);
    }

    public function employments()
    {
        return $this->hasMany(Employment::class);
    }

    // Emploi actuel
    public function currentEmployment()
    {
        return $this->hasOne(Employment::class)->where('is_current', true);
    }

    public function wasEmployedAt($date)
    {
        return $this->employments()
            ->during($date)
            ->exists();
    }
}