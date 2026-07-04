<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentDataChild extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_data_children';

    protected $fillable = [
        'content_data_id',
        'source_key',
        'row_key',
        'sort_order',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function contentData()
    {
        return $this->belongsTo(ContentData::class, 'content_data_id');
    }

    public function fields()
    {
        return $this->hasMany(ContentDataChildField::class, 'content_data_child_id')->orderBy('id');
    }
}
