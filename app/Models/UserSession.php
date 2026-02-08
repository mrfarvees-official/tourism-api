<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id','session_id',
        'device_name','device_type','os','browser','user_agent',
        'ip_first','ip_last','last_seen_at','expires_at',
        'revoked_at','revoke_reason'
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];
}
