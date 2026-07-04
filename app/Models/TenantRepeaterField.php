<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantRepeaterField extends Model
{
    use SoftDeletes;

    protected $table = 'tenant_repeater_fields';

    protected $fillable = [
        'tenant_id',
        'repeater_item_id',
        'field_key',
        'field_type',
        'value_string',
        'value_text',
        'value_int',
        'value_bool',
        'value_decimal',
        'value_asset_id',
    ];
}
