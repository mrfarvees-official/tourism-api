<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'trial_ends_at',
        'suspended_at',
        'meta',
    ];

    
    /**
     * Define many-to-many relationship with tenant_users
     * 
     * @return array<Tenant>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'tenant_id', 'user_id')
            ->using(TenantUser::class)
            ->withPivot([
                'role',
                'status',
                'joined_at',
                'last_seen_at',
                'invited_by_user_id',
                'deleted_at',
            ])
            ->withTimestamps();
    }

    public function theme()
    {
        return $this->hasOne(TenantTheme::class, 'tenant_id');
    }

    public function assets()
    {
        return $this->hasMany(TenantAssets::class, 'tenant_id');
    }

    public function media()
    {
        return $this->assets()->where('kind', 'image');
    }

    public function brand()
    {
        return $this->hasOne(TenantBranding::class, 'tenant_id');
    }

    public function domain()
    {
        return $this->hasOne(TenantDomain::class, 'tenant_id');
    }

    public function owner(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id')
            ->wherePivot('role', 'tenant_owner')
            ->wherePivotNull('deleted_at');
    }
}
