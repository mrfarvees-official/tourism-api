<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantInboxMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tenant_inbox_messages';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'page_slug',
        'source',
        'status',
        'read_at',
        'replied_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
