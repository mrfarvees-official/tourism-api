<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBootstrapResource extends JsonResource 
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return collect([
            // Return tenant details optional but can validate
            'tenant' => [
                'key' => $this->key,
                'name' => $this->name,
            ],
            // Current tenant theme details
            'theme' => $this->whenLoaded('theme', fn () => [
                'mode_default' => $this->theme?->mode_default,
                'tokens' => $this->theme?->tokens,
                'custom_css' => $this->theme?->custom_css,
            ]),
        ])->all();
    }
}