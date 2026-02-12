<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'product_code',
        'unit_price',
        'quantity',
        'total_price',
        'tax_rate',
        'tax_amount',
    ];
}
