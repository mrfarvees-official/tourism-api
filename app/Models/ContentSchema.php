<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentSchema extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_schema';
    protected $fillable = [
        'tenant_id',
        'name',
        'menu',
        'schema',
        'version',
        'status'
    ];
}
