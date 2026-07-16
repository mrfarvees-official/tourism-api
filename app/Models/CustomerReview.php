<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReview extends Model
{
    use SoftDeletes;

    protected $table = 'customer_reviews';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'booking_id',
        'booking_reference',
        'title',
        'message',
        'rating',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function toCustomerArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'bookingId' => (string) ($this->booking_id ?? $this->booking_reference ?? ''),
            'booking_id' => $this->booking_id,
            'bookingReference' => $this->booking_reference,
            'booking_reference' => $this->booking_reference,
            'title' => $this->title,
            'message' => $this->message,
            'rating' => $this->rating,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
