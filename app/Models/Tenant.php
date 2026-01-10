<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';
    protected $fillable = [
        'key',
        'name',
        'status',
        'timezone',
        'locale',
        'created_by_user_id',
        'trail_ends_at',
        'suspended_at',
        'meta',
    ];
}
