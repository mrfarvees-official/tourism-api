<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantPages extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_pages';
    protected $fillable = [
        'tenant_id',
        'slug',
        'title',
        'schema',
        'seo',
        'status',
        'meta_title',
        'meta_description',
        'og_asset_id',
        'published_at'
    ];

    protected $casts = [
        'schema' => 'array',
        'seo' => 'array',
        'published_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function components()
    {
        return $this->hasMany(TenantPageComponent::class, 'page_id');
    }
}
