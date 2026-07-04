<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentDataChildField extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'content_data_child_fields';

    protected $fillable = [
        'content_data_child_id',
        'field_key',
        'source_column',
        'field_type',
        'value_string',
        'value_text',
        'value_int',
        'value_bool',
        'value_decimal',
        'value_asset_id',
    ];

    public function child()
    {
        return $this->belongsTo(ContentDataChild::class, 'content_data_child_id');
    }
}
