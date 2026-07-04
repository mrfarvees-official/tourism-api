<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentData extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_data';
    protected $fillable = ['content_schema_id', 'data'];
    protected $casts = [
        'data' => 'array',
    ];

    public function contentSchema()
    {
        return $this->belongsTo(ContentSchema::class, 'content_schema_id');
    }

    public function children()
    {
        return $this->hasMany(ContentDataChild::class, 'content_data_id')->orderBy('sort_order');
    }
}
