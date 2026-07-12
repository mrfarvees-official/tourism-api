<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Stay extends Model
{
    use SoftDeletes;

    protected $table = 'stays';

    protected $fillable = [
        'tenant_id',
        'slug',
        'stay_name',
        'description',
        'stay_type',
        'location',
        'room_type',
        'amenities',
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
                'slug' => 'ella-view-resort',
                'stay_name' => 'Ella View Resort',
                'description' => 'Hill country stay option with family rooms and breakfast.',
                'stay_type' => 'Resort',
                'location' => 'Ella',
                'room_type' => 'Family room',
                'amenities' => 'Breakfast, valley views, pool',
                'price_label' => 'LKR 38,000',
                'price_value' => 38000,
                'image_url' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
                'story' => 'A relaxed ridge-line stay with strong views.',
            ],
            [
                'slug' => 'galle-fort-boutique',
                'stay_name' => 'Galle Fort Boutique',
                'description' => 'Walkable old-town stay inside the fort area.',
                'stay_type' => 'Boutique hotel',
                'location' => 'Galle',
                'room_type' => 'Heritage room',
                'amenities' => 'Wi-Fi, courtyard, breakfast',
                'price_label' => 'LKR 46,000',
                'price_value' => 46000,
                'image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
                'story' => 'Compact heritage stay for south coast walking trips.',
            ],
            [
                'slug' => 'mirissa-beach-house',
                'stay_name' => 'Mirissa Beach House',
                'description' => 'Beachfront guesthouse with relaxed coastal pacing.',
                'stay_type' => 'Guesthouse',
                'location' => 'Mirissa',
                'room_type' => 'Ocean-facing room',
                'amenities' => 'Beach access, breakfast, transfers',
                'price_label' => 'LKR 28,000',
                'price_value' => 28000,
                'image_url' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
                'story' => 'Easy beach base with direct access to the coast.',
            ],
            [
                'slug' => 'kandy-heritage-inn',
                'stay_name' => 'Kandy Heritage Inn',
                'description' => 'Central hill-city stay close to the lake and temples.',
                'stay_type' => 'Inn',
                'location' => 'Kandy',
                'room_type' => 'Standard room',
                'amenities' => 'Breakfast, central location, parking',
                'price_label' => 'LKR 24,500',
                'price_value' => 24500,
                'image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
                'story' => 'Practical stay for temple and lake visits.',
            ],
        ];
    }

    public static function normalizeSlug(?string $value, int|string|null $fallback = null): string
    {
        $slug = Str::slug((string) $value);
        return $slug !== '' ? $slug : 'stay-' . ($fallback ?? Str::random(6));
    }

    public function toTourismArray(): array
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->stay_name,
            'subtitle' => $this->stay_type,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'amount' => $this->price_label ?: ($this->price_value !== null ? 'LKR ' . number_format((int) $this->price_value) : null),
            'image' => $imageUrl ?: null,
            'imageUrl' => $imageUrl ?: null,
            'image_url' => $imageUrl ?: null,
            'href' => '/stays/' . $this->slug,
            'fields' => [
                'location' => $this->location,
                'room_type' => $this->room_type,
                'amenities' => $this->amenities,
                'story' => $this->story,
            ],
            'location' => $this->location,
            'room_type' => $this->room_type,
            'amenities' => $this->amenities,
            'story' => $this->story,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
