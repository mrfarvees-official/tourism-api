<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $publicId = $this->public_id ?: $this->path;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'kind' => $this->kind,
            'disk' => $this->disk,
            'path' => $this->path,
            'public_id' => $publicId,
            'secure_url' => $this->secure_url,
            'url' => $this->secure_url ?: $this->cloudinaryUrl($publicId),
            'resource_type' => $this->resource_type,
            'mime' => $this->mime,
            'size' => $this->size,
            'label' => $this->label,
            'original_name' => $this->original_name,
            'cloudinary_version' => $this->cloudinary_version,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function cloudinaryUrl(?string $publicId): ?string
    {
        $cloudName = config('services.cloudinary.cloud_name');
        if (!$cloudName || !$publicId) {
            return null;
        }

        return "https://res.cloudinary.com/{$cloudName}/image/upload/{$publicId}";
    }
}
