<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantAssets extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_assets';
    protected $fillable = [
        'tenant_id',
        'kind',
        'disk',
        'path',
        'public_id',
        'secure_url',
        'resource_type',
        'mime',
        'size',
        'label',
        'original_name',
        'cloudinary_version',
    ];

    protected $casts = [
        'size' => 'integer',
        'cloudinary_version' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
