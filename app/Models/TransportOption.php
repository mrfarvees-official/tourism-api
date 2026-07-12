<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TransportOption extends Model
{
    use SoftDeletes;

    protected $table = 'transport_options';

    protected $fillable = [
        'tenant_id',
        'slug',
        'transport_name',
        'description',
        'transport_type',
        'capacity',
        'coverage',
        'vehicle',
        'pricing_model',
        'price_label',
        'price_value',
        'image_url',
        'featured',
        'status',
        'story',
    ];

    protected $casts = [
        'price_value' => 'integer',
        'featured' => 'boolean',
    ];

    public static function defaultSeedRows(): array
    {
        return [
            [
                'slug' => 'private-van',
                'transport_name' => 'Private Van',
                'description' => 'Comfortable private van for family and small-group routes.',
                'transport_type' => 'Van',
                'capacity' => '6 seats',
                'coverage' => 'Island-wide',
                'vehicle' => 'Air-conditioned van',
                'pricing_model' => 'Per day',
                'price_label' => 'LKR 28,000/day',
                'price_value' => 28000,
                'image_url' => 'https://images.unsplash.com/photo-1542228262-3d663b306f8d?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
                'story' => 'Flexible private van support for island routes.',
            ],
            [
                'slug' => 'safari-jeep',
                'transport_name' => 'Safari Jeep',
                'description' => 'Open safari-ready vehicle for wildlife trips.',
                'transport_type' => 'Jeep',
                'capacity' => '4 seats',
                'coverage' => 'Parks and reserves',
                'vehicle' => 'Safari jeep',
                'pricing_model' => 'Per trip',
                'price_label' => 'LKR 22,500',
                'price_value' => 22500,
                'image_url' => 'https://images.unsplash.com/photo-1518709766631-a6a7f45921c3?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
                'story' => 'Built for early-morning safari movement.',
            ],
            [
                'slug' => 'luxury-sedan',
                'transport_name' => 'Luxury Sedan',
                'description' => 'Premium chauffeur support with polished vehicle finishes.',
                'transport_type' => 'Sedan',
                'capacity' => '3 seats',
                'coverage' => 'Colombo, south coast',
                'vehicle' => 'Luxury sedan',
                'pricing_model' => 'Daily',
                'price_label' => 'LKR 34,000/day',
                'price_value' => 34000,
                'image_url' => 'https://images.unsplash.com/photo-1538688423619-a81d3f23454b?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
                'story' => 'High-touch road movement for premium guests.',
            ],
            [
                'slug' => 'coach-bus',
                'transport_name' => 'Coach Bus',
                'description' => 'Group transport for larger parties and events.',
                'transport_type' => 'Bus',
                'capacity' => '20 seats',
                'coverage' => 'Island-wide',
                'vehicle' => 'Coach bus',
                'pricing_model' => 'Project',
                'price_label' => 'Quote on request',
                'price_value' => 0,
                'image_url' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'draft',
                'story' => 'Useful for events and group itineraries.',
            ],
        ];
    }

    public static function normalizeSlug(?string $value, int|string|null $fallback = null): string
    {
        $slug = Str::slug((string) $value);
        return $slug !== '' ? $slug : 'transport-' . ($fallback ?? Str::random(6));
    }

    public function toTourismArray(): array
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->transport_name,
            'subtitle' => $this->transport_type ?: $this->capacity,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'amount' => $this->price_label ?: ($this->price_value !== null ? 'LKR ' . number_format((int) $this->price_value) : null),
            'image' => $imageUrl ?: null,
            'imageUrl' => $imageUrl ?: null,
            'image_url' => $imageUrl ?: null,
            'href' => '/transport/' . $this->slug,
            'fields' => [
                'capacity' => $this->capacity,
                'coverage' => $this->coverage,
                'vehicle' => $this->vehicle,
                'pricing_model' => $this->pricing_model,
                'story' => $this->story,
            ],
            'capacity' => $this->capacity,
            'coverage' => $this->coverage,
            'vehicle' => $this->vehicle,
            'pricing_model' => $this->pricing_model,
            'story' => $this->story,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
