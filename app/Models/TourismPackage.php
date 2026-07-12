<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TourismPackage extends Model
{
    use SoftDeletes;

    protected $table = 'packages';

    protected $fillable = [
        'tenant_id',
        'slug',
        'package_name',
        'description',
        'duration',
        'route_summary',
        'inclusions',
        'best_for',
        'pace',
        'highlights',
        'story',
        'price_label',
        'price_value',
        'image_url',
        'featured',
        'status',
    ];

    protected $casts = [
        'price_value' => 'integer',
        'featured' => 'boolean',
    ];

    public static function defaultSeedRows(): array
    {
        return [
            [
                'slug' => 'sri-lanka-highlights',
                'package_name' => 'Sri Lanka Highlights',
                'description' => 'Culture, tea country, wildlife, and coast in one practical itinerary.',
                'duration' => '7 days / 6 nights',
                'route_summary' => 'Sigiriya, Kandy, Ella, Mirissa',
                'inclusions' => 'Private transfers, guide support, selected stays',
                'best_for' => 'Couples, families, first-time visitors',
                'pace' => 'Comfortable',
                'highlights' => 'Rock fortress, tea estates, south coast sunsets',
                'story' => 'Built for travelers who want one clean route with cultural depth, scenic travel, and enough breathing room to enjoy each stop instead of rushing through it.',
                'price_label' => 'LKR 185,000',
                'price_value' => 185000,
                'image_url' => 'https://images.unsplash.com/photo-1504639725590-34d0984388bd?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
            ],
            [
                'slug' => 'hill-country-weekend',
                'package_name' => 'Hill Country Weekend',
                'description' => 'A compact private trip with train scenery, hikes, and tea estates.',
                'duration' => '3 days / 2 nights',
                'route_summary' => 'Kandy, Nuwara Eliya, Ella',
                'inclusions' => 'Rail-side stays, scenic drives, tea tastings',
                'best_for' => 'Quick escapes and anniversary trips',
                'pace' => 'Leisurely',
                'highlights' => 'Train views, cool air, tea country stops',
                'story' => 'A short mountain reset focused on cool air, rail views, and a couple of unhurried experiences that feel premium without becoming overly complex.',
                'price_label' => 'LKR 72,000',
                'price_value' => 72000,
                'image_url' => 'https://images.unsplash.com/photo-1518467166778-b88f373ffec7?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
            ],
            [
                'slug' => 'coastal-escape',
                'package_name' => 'Coastal Escape',
                'description' => 'A relaxed south coast loop with beach time, dining, and easy transfers.',
                'duration' => '5 days / 4 nights',
                'route_summary' => 'Galle, Mirissa, Bentota',
                'inclusions' => 'Beach stays, airport transfer, coastal driver',
                'best_for' => 'Friends, couples, short breaks',
                'pace' => 'Relaxed',
                'highlights' => 'Fort walks, whale watching, sunset beaches',
                'story' => 'Designed to keep the coast simple and calm with time for old-town walks, seafood dinners, and open-ended beach afternoons.',
                'price_label' => 'LKR 128,000',
                'price_value' => 128000,
                'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
            ],
            [
                'slug' => 'cultural-triangle-tour',
                'package_name' => 'Cultural Triangle Tour',
                'description' => 'Ancient capitals, sacred sites, and heritage cycling routes.',
                'duration' => '6 days / 5 nights',
                'route_summary' => 'Anuradhapura, Sigiriya, Polonnaruwa',
                'inclusions' => 'Private guide, monument entries, heritage transport',
                'best_for' => 'History-focused travelers',
                'pace' => 'Balanced',
                'highlights' => 'Sacred stupas, temple visits, bicycle tours',
                'story' => 'A heritage-first route that gives the traveler enough time at each site to understand the story instead of just ticking off landmarks.',
                'price_label' => 'LKR 156,000',
                'price_value' => 156000,
                'image_url' => 'https://images.unsplash.com/photo-1582550555028-64b1e7e5f1d9?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
            ],
            [
                'slug' => 'wildlife-and-waterfalls',
                'package_name' => 'Wildlife and Waterfalls',
                'description' => 'A safari-led route paired with hill-country waterfalls and tea stops.',
                'duration' => '4 days / 3 nights',
                'route_summary' => 'Yala, Ella, Udawalawe',
                'inclusions' => 'Safari jeep, private driver, selected stays',
                'best_for' => 'Nature and wildlife travelers',
                'pace' => 'Active',
                'highlights' => 'Leopard country, waterfall viewpoints, elephants',
                'story' => 'Built to balance the excitement of safari mornings with calm hill-country afternoons and a scenic return south.',
                'price_label' => 'LKR 142,000',
                'price_value' => 142000,
                'image_url' => 'https://images.unsplash.com/photo-1544735716-392fe2489ffa?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
            ],
            [
                'slug' => 'luxury-private-tour',
                'package_name' => 'Luxury Private Tour',
                'description' => 'High-touch travel with premium stays, private guides, and curated timing.',
                'duration' => '8 days / 7 nights',
                'route_summary' => 'Colombo, Galle, Bentota, Ella',
                'inclusions' => 'Luxury hotels, private chauffeur, concierge support',
                'best_for' => 'Premium travelers',
                'pace' => 'Flexible',
                'highlights' => 'Boutique stays, private dining, scenic transfers',
                'story' => 'A polished route for guests who want the trip to feel effortless, spacious, and elevated at every stop.',
                'price_label' => 'LKR 312,000',
                'price_value' => 312000,
                'image_url' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
            ],
            [
                'slug' => 'weekend-reset',
                'package_name' => 'Weekend Reset',
                'description' => 'Two calm nights with a low-friction route and room for spa time.',
                'duration' => '3 days / 2 nights',
                'route_summary' => 'Kandy',
                'inclusions' => 'City stay, transfers, flexible schedule',
                'best_for' => 'Short rest trips',
                'pace' => 'Slow',
                'highlights' => 'Lake walks, temple time, slow breakfasts',
                'story' => 'A minimalist itinerary for when the goal is not to cover distance but to return home feeling reset.',
                'price_label' => 'LKR 64,000',
                'price_value' => 64000,
                'image_url' => 'https://images.unsplash.com/photo-1576487244612-345da0d365eb?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
            ],
            [
                'slug' => 'family-discovery',
                'package_name' => 'Family Discovery',
                'description' => 'Family-friendly pacing with short transfers, roomy stays, and easy meals.',
                'duration' => '6 days / 5 nights',
                'route_summary' => 'Colombo, Bentota, Galle',
                'inclusions' => 'Family rooms, child seats, private driver',
                'best_for' => 'Multi-generation families',
                'pace' => 'Flexible',
                'highlights' => 'Beach time, aquarium stops, short sightseeing days',
                'story' => 'Designed around family rhythm, this package keeps transfers short, adds flexible meal timing, and leaves room for rest, pools, and optional activities.',
                'price_label' => 'LKR 198,000',
                'price_value' => 198000,
                'image_url' => 'https://images.unsplash.com/photo-1519887828074-9dff7c4b3f31?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
            ],
            [
                'slug' => 'tea-trails-escape',
                'package_name' => 'Tea Trails Escape',
                'description' => 'A tea-country route built around plantation walks and cool weather stays.',
                'duration' => '4 days / 3 nights',
                'route_summary' => 'Nuwara Eliya, Hatton',
                'inclusions' => 'Tea estate visits, scenic train support',
                'best_for' => 'Tea lovers and scenic travelers',
                'pace' => 'Leisurely',
                'highlights' => 'Plantation lunches, lakeside walks, highland views',
                'story' => 'A slower mountain story centered on tea heritage, misty landscapes, and quiet mornings above the valley.',
                'price_label' => 'LKR 110,000',
                'price_value' => 110000,
                'image_url' => 'https://images.unsplash.com/photo-1493246507139-91e8fad9978e?auto=format&fit=crop&w=1200&q=80',
                'featured' => true,
                'status' => 'active',
            ],
            [
                'slug' => 'festival-city-break',
                'package_name' => 'Festival City Break',
                'description' => 'A compact Colombo-focused route for events, dining, and short cultural stops.',
                'duration' => '2 days / 1 night',
                'route_summary' => 'Colombo',
                'inclusions' => 'City hotel, airport transfer, event support',
                'best_for' => 'Short business and event trips',
                'pace' => 'Fast',
                'highlights' => 'City dining, art spaces, waterfront walks',
                'story' => 'A city break that keeps the logistics light and the schedule open for events, food, and quick discovery.',
                'price_label' => 'LKR 54,000',
                'price_value' => 54000,
                'image_url' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?auto=format&fit=crop&w=1200&q=80',
                'featured' => false,
                'status' => 'active',
            ],
        ];
    }

    public static function normalizeSlug(?string $value, int|string|null $fallback = null): string
    {
        $slug = Str::slug((string) $value);

        if ($slug !== '') {
            return $slug;
        }

        return 'package-' . ($fallback ?? Str::random(6));
    }

    public function toTourismArray(): array
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->package_name,
            'subtitle' => $this->duration,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'amount' => $this->price_label ?: ($this->price_value !== null ? 'LKR ' . number_format((int) $this->price_value) : null),
            'image' => $imageUrl ?: null,
            'imageUrl' => $imageUrl ?: null,
            'image_url' => $imageUrl ?: null,
            'href' => '/packages/' . $this->slug,
            'fields' => [
                'duration' => $this->duration,
                'route' => $this->route_summary,
                'includes' => $this->inclusions,
                'best_for' => $this->best_for,
                'pace' => $this->pace,
                'highlights' => $this->highlights,
                'story' => $this->story,
            ],
            'routeSummary' => $this->route_summary,
            'route_summary' => $this->route_summary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
