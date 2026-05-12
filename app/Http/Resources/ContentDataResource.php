<?php

namespace App\Http\Resources;

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
        return collect([
            'id' => $this->id,
            'schema_id' => $this->schema_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ])->all();
    }
}
