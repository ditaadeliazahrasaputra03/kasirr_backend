<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reference',
        'external_id',
        'amount',
        'final_amount',
        'status',
        'checkout_url',
        'qr_image_url',
        'qr_string',
        'customer_name',
        'customer_email',
        'description',
        'paid_at',
        'expired_at',
        'raw',
    ];

    protected $casts = [
        'amount' => 'integer',
        'final_amount' => 'integer',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'raw' => 'array',
    ];

    public function isPaid(): bool
    {
        return strtoupper($this->status) === 'PAID';
    }
}
