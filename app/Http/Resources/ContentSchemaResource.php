<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentSchemaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'menu' => $this->menu,
            'source_key' => $this->sourceKey(),
            'schema' => $this->schema,
            'version' => $this->version,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }

    private function sourceKey(): ?string
    {
        $schema = $this->schema;

        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }

        if (!is_array($schema)) {
            return null;
        }

        $meta = $schema['meta'] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        $sourceKey = $meta['sourceKey'] ?? null;
        if (!is_string($sourceKey)) {
            return null;
        }

        $sourceKey = trim($sourceKey);
        return $sourceKey !== '' ? $sourceKey : null;
    }
}
