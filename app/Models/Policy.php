<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Policy extends Model
{
    use SoftDeletes;

    protected $table = 'pbac_policy';
    protected $fillable = [
        'tenant_id',
        'name',
        'effect',
        'priority',
        'is_enabled',
        'permission_id',
        'description'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
