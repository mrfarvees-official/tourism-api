<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TourismActivity extends Model
{
    use SoftDeletes;

    protected $table = 'activities';

    protected $fillable = [
        'tenant_id','slug','activity_name','description','activity_type','duration','best_for','pace','season','highlights','story','price_label','price_value','image_url','featured','status',
    ];

    protected $casts = [
        'price_value' => 'integer',
        'featured' => 'boolean',
    ];

    public static function defaultSeedRows(): array
    {
        return [
            ['slug'=>'tea-estate-walk','activity_name'=>'Tea Estate Walk','description'=>'Guided estate walk with tea tasting.','activity_type'=>'2 hours','duration'=>'2 hours','best_for'=>'Hill-country itineraries','pace'=>'Easy','season'=>'December to April','highlights'=>'Tea tasting, estate stories','story'=>'A host-led walk through the estate.','price_label'=>'LKR 8,500','price_value'=>8500,'image_url'=>'https://images.unsplash.com/photo-1515822205212-7b3b38b9e8d6?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'whale-watching','activity_name'=>'Whale Watching','description'=>'Seasonal ocean experience.','activity_type'=>'Half day','duration'=>'Half day','best_for'=>'Coastal stays','pace'=>'Moderate','season'=>'November to April','highlights'=>'Early boat departure','story'=>'An early-morning boat departure timed around calmer seas.','price_label'=>'LKR 18,000','price_value'=>18000,'image_url'=>'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'yala-safari','activity_name'=>'Yala Safari','description'=>'Jeep safari for wildlife sightings.','activity_type'=>'Full day','duration'=>'Full day','best_for'=>'Wildlife enthusiasts','pace'=>'Active','season'=>'February to July','highlights'=>'Leopards, elephants','story'=>'A classic wildlife day shaped around early starts.','price_label'=>'LKR 24,000','price_value'=>24000,'image_url'=>'https://images.unsplash.com/photo-1547970810-dc1f5f6d2ea3?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'sigiriya-climb','activity_name'=>'Sigiriya Climb','description'=>'Guided climb with heritage interpretation.','activity_type'=>'Half day','duration'=>'Half day','best_for'=>'History and photography','pace'=>'Moderate','season'=>'May to September','highlights'=>'Lion rock fortress, summit views','story'=>'Best started early for the fortress context.','price_label'=>'LKR 10,500','price_value'=>10500,'image_url'=>'https://images.unsplash.com/photo-1582550555028-64b1e7e5f1d9?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'galle-fort-walk','activity_name'=>'Galle Fort Walk','description'=>'Walking tour through the fort and museums.','activity_type'=>'2 hours','duration'=>'2 hours','best_for'=>'Heritage travelers','pace'=>'Easy','season'=>'November to April','highlights'=>'Dutch fort, lanes','story'=>'A compact historical walk.','price_label'=>'LKR 6,500','price_value'=>6500,'image_url'=>'https://images.unsplash.com/photo-1580708105257-29a0d2c4ccf3?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'ella-hike','activity_name'=>'Ella Hike','description'=>'Scenic hike to viewpoints and waterfalls.','activity_type'=>'Half day','duration'=>'Half day','best_for'=>'Light adventure travelers','pace'=>'Moderate','season'=>'December to April','highlights'=>'Little Adam\'s Peak, waterfalls','story'=>'A hillside walk with enough movement to feel adventurous.','price_label'=>'LKR 7,500','price_value'=>7500,'image_url'=>'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'cooking-experience','activity_name'=>'Cooking Experience','description'=>'Local home-style cooking session.','activity_type'=>'3 hours','duration'=>'3 hours','best_for'=>'Food-focused travelers','pace'=>'Relaxed','season'=>'Year round','highlights'=>'Market visits, family recipes','story'=>'A meal-centered activity that makes the trip more personal.','price_label'=>'LKR 9,000','price_value'=>9000,'image_url'=>'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'surf-lesson','activity_name'=>'Surf Lesson','description'=>'Beginner-friendly surf coaching.','activity_type'=>'2 hours','duration'=>'2 hours','best_for'=>'Beach travelers','pace'=>'Active','season'=>'April to October','highlights'=>'Board rental, safety briefing','story'=>'A flexible lesson that keeps the focus on safety and fun.','price_label'=>'LKR 12,000','price_value'=>12000,'image_url'=>'https://images.unsplash.com/photo-1502680390469-be75c86b636f?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'boat-lagoon-tour','activity_name'=>'Boat Lagoon Tour','description'=>'Gentle lagoon and mangrove boat ride.','activity_type'=>'Half day','duration'=>'Half day','best_for'=>'Families and nature lovers','pace'=>'Easy','season'=>'Year round','highlights'=>'Mangroves, birdwatching','story'=>'A quiet water experience ideal for a slower afternoon.','price_label'=>'LKR 11,500','price_value'=>11500,'image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'train-journey-assist','activity_name'=>'Train Journey Assist','description'=>'Support for scenic train segments.','activity_type'=>'Logistics','duration'=>'Flexible','best_for'=>'Rail travelers','pace'=>'Flexible','season'=>'Year round','highlights'=>'Station pickup, luggage handling','story'=>'Makes the scenic train pieces easier to enjoy.','price_label'=>'LKR 5,500','price_value'=>5500,'image_url'=>'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
        ];
    }

    public static function normalizeSlug(?string $value, int|string|null $fallback = null): string
    {
        $slug = Str::slug((string) $value);
        return $slug !== '' ? $slug : 'activity-' . ($fallback ?? Str::random(6));
    }

    public function toTourismArray(): array
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->activity_name,
            'subtitle' => $this->activity_type,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'amount' => $this->price_label ?: ($this->price_value !== null ? 'LKR ' . number_format((int) $this->price_value) : null),
            'image' => $imageUrl ?: null,
            'imageUrl' => $imageUrl ?: null,
            'image_url' => $imageUrl ?: null,
            'href' => '/activities/' . $this->slug,
            'fields' => [
                'duration' => $this->duration,
                'best_for' => $this->best_for,
                'pace' => $this->pace,
                'season' => $this->season,
                'highlights' => $this->highlights,
                'story' => $this->story,
            ],
            'duration' => $this->duration,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
