<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantUser extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_user';
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'last_seen_at',
        'invited_by_user_id',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
