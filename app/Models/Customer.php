<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'tenant_id',
        'full_name',
        'email',
        'phone',
        'nationality',
        'passport_number',
        'preferred_language',
        'loyalty_tier',
        'emergency_contact',
        'address',
    ];

    public function toTourismArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'full_name' => $this->full_name,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'nationality' => $this->nationality,
            'passport_number' => $this->passport_number,
            'preferred_language' => $this->preferred_language,
            'loyalty_tier' => $this->loyalty_tier,
            'emergency_contact' => $this->emergency_contact,
            'address' => $this->address,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
