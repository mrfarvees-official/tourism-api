<?php

namespace App\Http\Resources;

use App\Models\ContentDataChild;
use App\Models\ContentDataChildField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $children = $this->relationLoaded('children') ? $this->children : collect();

        return collect([
            'id' => $this->id,
            'content_schema_id' => $this->content_schema_id,
            'schema_id' => $this->content_schema_id,
            'schema_blueprint' => $this->relationLoaded('contentSchema') ? $this->contentSchema?->schema : null,
            'data' => $this->data,
            'children' => $children->map(fn (ContentDataChild $child) => [
                'id' => $child->id,
                'content_data_id' => $child->content_data_id,
                'source_key' => $child->source_key,
                'row_key' => $child->row_key,
                'sort_order' => $child->sort_order,
                'payload' => $child->payload,
                'data' => $this->childFieldMap($child),
                'fields' => $child->relationLoaded('fields')
                    ? $child->fields->map(fn (ContentDataChildField $field) => [
                        'id' => $field->id,
                        'field_key' => $field->field_key,
                        'source_column' => $field->source_column,
                        'field_type' => $field->field_type,
                        'value_string' => $field->value_string,
                        'value_text' => $field->value_text,
                        'value_int' => $field->value_int,
                        'value_bool' => $field->value_bool,
                        'value_decimal' => $field->value_decimal,
                        'value_asset_id' => $field->value_asset_id,
                    ])->values()
                    : [],
            ])->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ])->all();
    }

    private function childFieldMap(ContentDataChild $child): array
    {
        if (!$child->relationLoaded('fields')) {
            return $child->payload ?? [];
        }

        $mapped = [];
        foreach ($child->fields as $field) {
            $sourceKey = $field->source_column ?: $field->field_key;
            $mapped[$sourceKey] = $field->value_bool
                ?? $field->value_int
                ?? $field->value_decimal
                ?? $field->value_string
                ?? $field->value_text
                ?? $field->value_asset_id;
        }

        return $mapped ?: ($child->payload ?? []);
    }
}
