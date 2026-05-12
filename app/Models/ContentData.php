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

    public function contentSchema() 
    {
        return $this->belongsTo(ContentSchema::class, 'content_schema_id');
    }
}
