<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPayment extends Model
{
    protected $table = 'customer_payments';

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'booking_reference',
        'customer_id',
        'amount',
        'currency',
        'payment_method',
        'payment_brand',
        'card_last4',
        'card_holder_name',
        'status',
        'provider_reference',
        'payment_payload',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_payload' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
