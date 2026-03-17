<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'person_id',
        'customer_number',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}