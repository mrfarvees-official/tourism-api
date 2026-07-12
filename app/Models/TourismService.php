<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TourismService extends Model
{
    use SoftDeletes;

    protected $table = 'tourism_services';

    protected $fillable = [
        'tenant_id','slug','service_name','description','service_type','coverage','vehicle','response_time','pricing_model','price_label','price_value','story','image_url','featured','status',
    ];

    protected $casts = [
        'price_value' => 'integer',
        'featured' => 'boolean',
    ];

    public static function defaultSeedRows(): array
    {
        return [
            ['slug'=>'airport-transfer','service_name'=>'Airport Transfer','description'=>'Private pickup and drop-off.','service_type'=>'Fixed price','coverage'=>'Colombo airport','vehicle'=>'Sedan or van','response_time'=>'Flight-aware','pricing_model'=>'Per transfer','price_label'=>'LKR 14,500','price_value'=>14500,'story'=>'Simple airport logistics.','image_url'=>'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'private-chauffeur','service_name'=>'Private Chauffeur','description'=>'Full-day driver service.','service_type'=>'Per day','coverage'=>'Island-wide','vehicle'=>'Private car or van','response_time'=>'Flexible','pricing_model'=>'Daily','price_label'=>'LKR 22,000','price_value'=>22000,'story'=>'Driver stays with the trip.','image_url'=>'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'luxury-driver','service_name'=>'Luxury Driver Service','description'=>'Premium vehicle support.','service_type'=>'Premium','coverage'=>'Colombo, south coast','vehicle'=>'Luxury sedan','response_time'=>'Priority planning','pricing_model'=>'Daily','price_label'=>'LKR 34,000','price_value'=>34000,'story'=>'Polished road support.','image_url'=>'https://images.unsplash.com/photo-1538688423619-a81d3f23454b?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'family-van-support','service_name'=>'Family Van Support','description'=>'Spacious family transport.','service_type'=>'Per day','coverage'=>'Popular circuits','vehicle'=>'Air-conditioned van','response_time'=>'Child seat on request','pricing_model'=>'Daily','price_label'=>'LKR 26,500','price_value'=>26500,'story'=>'Roomy family movement.','image_url'=>'https://images.unsplash.com/photo-1542228262-3d663b306f8d?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'rail-support','service_name'=>'Rail Transfer Support','description'=>'Support for scenic train segments.','service_type'=>'Logistics','coverage'=>'Kandy, Nanu Oya, Ella','vehicle'=>'Transfer van or car','response_time'=>'Timed to trains','pricing_model'=>'Per transfer','price_label'=>'LKR 16,500','price_value'=>16500,'story'=>'Keeps the train leg simple.','image_url'=>'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'wedding-concierge','service_name'=>'Wedding Concierge','description'=>'Guest transfers and venue coordination.','service_type'=>'Concierge','coverage'=>'Beach venues','vehicle'=>'Guest fleet','response_time'=>'Event-day support','pricing_model'=>'Project','price_label'=>'LKR 98,000','price_value'=>98000,'story'=>'Transport for wedding events.','image_url'=>'https://images.unsplash.com/photo-1494972308805-463bc619d34e?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'multi-day-guide','service_name'=>'Multi-day Guide','description'=>'Guide support for heritage routes.','service_type'=>'Per trip','coverage'=>'Cultural Triangle','vehicle'=>'Guide with vehicle','response_time'=>'Route planning','pricing_model'=>'Trip','price_label'=>'LKR 42,000','price_value'=>42000,'story'=>'Keeps the trip story together.','image_url'=>'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'city-hourly-support','service_name'=>'City Hourly Support','description'=>'Hourly city driver support.','service_type'=>'Hourly','coverage'=>'Colombo','vehicle'=>'City car','response_time'=>'Short notice','pricing_model'=>'Hourly','price_label'=>'LKR 4,500 / hr','price_value'=>4500,'story'=>'Flexible city movement.','image_url'=>'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'active'],
            ['slug'=>'safari-logistics','service_name'=>'Safari Logistics','description'=>'Pickup timing and park entry flow.','service_type'=>'Logistics','coverage'=>'Yala, Udawalawe, Wilpattu','vehicle'=>'Safari-ready vehicle','response_time'=>'Dawn planning','pricing_model'=>'Per trip','price_label'=>'LKR 18,500','price_value'=>18500,'story'=>'Early safari support.','image_url'=>'https://images.unsplash.com/photo-1518709766631-a6a7f45921c3?auto=format&fit=crop&w=1200&q=80','featured'=>true,'status'=>'active'],
            ['slug'=>'custom-road-assist','service_name'=>'Custom Road Assist','description'=>'Custom routing for unusual itineraries.','service_type'=>'Custom','coverage'=>'Island-wide','vehicle'=>'Assigned by route','response_time'=>'On request','pricing_model'=>'Custom quote','price_label'=>'Custom','price_value'=>0,'story'=>'Flexible trip support.','image_url'=>'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=1200&q=80','featured'=>false,'status'=>'draft'],
        ];
    }

    public static function normalizeSlug(?string $value, int|string|null $fallback = null): string
    {
        $slug = Str::slug((string) $value);
        return $slug !== '' ? $slug : 'service-' . ($fallback ?? Str::random(6));
    }

    public function toTourismArray(): array
    {
        $imageUrl = trim((string) ($this->image_url ?? ''));

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->service_name,
            'subtitle' => $this->service_type,
            'description' => $this->description,
            'status' => $this->status,
            'featured' => (bool) $this->featured,
            'amount' => $this->price_label ?: ($this->price_value !== null ? 'LKR ' . number_format((int) $this->price_value) : null),
            'image' => $imageUrl ?: null,
            'imageUrl' => $imageUrl ?: null,
            'image_url' => $imageUrl ?: null,
            'href' => '/services/' . $this->slug,
            'fields' => [
                'coverage' => $this->coverage,
                'vehicle' => $this->vehicle,
                'response' => $this->response_time,
                'pricing_model' => $this->pricing_model,
                'story' => $this->story,
            ],
            'coverage' => $this->coverage,
            'responseTime' => $this->response_time,
            'response_time' => $this->response_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
