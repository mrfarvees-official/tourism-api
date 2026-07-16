<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'bookings';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'reference',
        'customer_name',
        'customer_email',
        'package_name',
        'package_slug',
        'destination',
        'destination_slug',
        'service_name',
        'service_slug',
        'activity_name',
        'activity_slug',
        'travel_date',
        'return_date',
        'adults',
        'children',
        'infants',
        'travelers_count',
        'total_amount',
        'paid_amount',
        'currency',
        'booking_status',
        'payment_status',
        'payment_due_date',
        'notes',
        'route_summary',
        'trip_story',
        'trip_highlights',
        'add_ons',
        'itinerary',
        'support_contact',
        'destination_story',
        'package_story',
        'service_story',
        'activity_story',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'return_date' => 'date',
        'payment_due_date' => 'date',
        'adults' => 'integer',
        'children' => 'integer',
        'infants' => 'integer',
        'travelers_count' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'trip_highlights' => 'array',
        'add_ons' => 'array',
        'itinerary' => 'array',
    ];

    public static function defaultSeedRows(): array
    {
        return [
            [
                'reference' => 'TBK-2026-000101',
                'customer_name' => 'Ayesha Khan',
                'customer_email' => 'ayesha.khan@example.com',
                'package_name' => 'Sri Lanka Highlights',
                'package_slug' => 'sri-lanka-highlights',
                'destination' => 'Sigiriya, Kandy, Ella, Mirissa',
                'destination_slug' => 'sigiriya-kandy-ella-mirissa',
                'service_name' => 'Airport Transfer',
                'service_slug' => 'airport-transfer',
                'activity_name' => 'Tea Estate Walk',
                'activity_slug' => 'tea-estate-walk',
                'travel_date' => '2026-08-14',
                'return_date' => '2026-08-20',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'travelers_count' => 2,
                'total_amount' => 370000,
                'paid_amount' => 185000,
                'currency' => 'LKR',
                'booking_status' => 'confirmed',
                'payment_status' => 'partial',
                'payment_due_date' => '2026-08-07',
                'notes' => 'Pickup requested from Bandaranaike International Airport.',
                'route_summary' => 'Sigiriya · Kandy · Ella · Mirissa',
                'trip_story' => 'Classic circuit with culture, tea country, and coast.',
                'trip_highlights' => ['Airport transfer', 'Private driver', 'Cultural guide'],
                'add_ons' => ['Airport transfer', 'Private driver', 'Cultural guide'],
                'itinerary' => ['Arrival in Colombo', 'Sigiriya day trip', 'Kandy temple trail', 'Ella tea country', 'South coast'],
                'support_contact' => 'operations@lankatrails.example',
                'destination_story' => 'Story-led route through the island.',
                'package_story' => 'Built for first-time visitors.',
                'service_story' => 'Simple airport logistics.',
                'activity_story' => 'Guided estate walk with tea tasting.',
            ],
            [
                'reference' => 'TBK-2026-000102',
                'customer_name' => 'Nimal Perera',
                'customer_email' => 'nimal.perera@example.com',
                'package_name' => 'Hill Country Weekend',
                'package_slug' => 'hill-country-weekend',
                'destination' => 'Nuwara Eliya, Ella',
                'destination_slug' => 'nuwara-eliya-ella',
                'service_name' => 'Private Chauffeur',
                'service_slug' => 'private-chauffeur',
                'activity_name' => 'Ella Hike',
                'activity_slug' => 'ella-hike',
                'travel_date' => '2026-08-22',
                'return_date' => '2026-08-24',
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'travelers_count' => 2,
                'total_amount' => 116000,
                'paid_amount' => 116000,
                'currency' => 'LKR',
                'booking_status' => 'confirmed',
                'payment_status' => 'paid',
                'payment_due_date' => '2026-08-20',
                'notes' => 'Child seat requested for the transfer.',
                'route_summary' => 'Kandy · Nuwara Eliya · Ella',
                'trip_story' => 'Short mountain escape with scenic views.',
                'trip_highlights' => ['Child seat', 'Train tickets'],
                'add_ons' => ['Child seat', 'Train tickets'],
                'itinerary' => ['Nanu Oya pickup', 'Tea estate walk', 'Ella day excursion'],
                'support_contact' => 'support@lankatrails.example',
                'destination_story' => 'Cool-weather hill-country story.',
                'package_story' => 'Rail-side stays and tea tastings.',
                'service_story' => 'Driver stays with the trip.',
                'activity_story' => 'Hillside walk with waterfall stops.',
            ],
            [
                'reference' => 'TBK-2026-000103',
                'customer_name' => 'Maya Silva',
                'customer_email' => 'maya.silva@example.com',
                'package_name' => 'Southern Coast Escape',
                'package_slug' => 'coastal-escape',
                'destination' => 'Mirissa, Galle',
                'destination_slug' => 'mirissa-galle',
                'service_name' => 'Family Van Support',
                'service_slug' => 'family-van-support',
                'activity_name' => 'Whale Watching',
                'activity_slug' => 'whale-watching',
                'travel_date' => '2026-09-04',
                'return_date' => '2026-09-08',
                'adults' => 2,
                'children' => 1,
                'infants' => 0,
                'travelers_count' => 3,
                'total_amount' => 248000,
                'paid_amount' => 0,
                'currency' => 'LKR',
                'booking_status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_due_date' => '2026-08-30',
                'notes' => 'Sea-view room preference.',
                'route_summary' => 'Galle · Mirissa · Bentota',
                'trip_story' => 'Easy coastal route with marine and beach time.',
                'trip_highlights' => ['Whale watching', 'Airport transfer'],
                'add_ons' => ['Whale watching', 'Airport transfer'],
                'itinerary' => ['Galle Fort', 'Mirissa beach time', 'Whale watching', 'Spa afternoon'],
                'support_contact' => 'bookings@lankatrails.example',
                'destination_story' => 'Historic port and beach days.',
                'package_story' => 'Relaxed south coast loop.',
                'service_story' => 'Spacious family transport.',
                'activity_story' => 'Seasonal ocean experience.',
            ],
            [
                'reference' => 'TBK-2026-000104',
                'customer_name' => 'Kamal Fernando',
                'customer_email' => 'kamal.fernando@example.com',
                'package_name' => 'Cultural Triangle Tour',
                'package_slug' => 'cultural-triangle-tour',
                'destination' => 'Anuradhapura, Sigiriya, Polonnaruwa',
                'destination_slug' => 'anuradhapura-sigiriya-polonnaruwa',
                'service_name' => 'Multi-day Guide',
                'service_slug' => 'multi-day-guide',
                'activity_name' => 'Sigiriya Climb',
                'activity_slug' => 'sigiriya-climb',
                'travel_date' => '2026-09-14',
                'return_date' => '2026-09-19',
                'adults' => 3,
                'children' => 0,
                'infants' => 0,
                'travelers_count' => 3,
                'total_amount' => 312000,
                'paid_amount' => 156000,
                'currency' => 'LKR',
                'booking_status' => 'confirmed',
                'payment_status' => 'partial',
                'payment_due_date' => '2026-09-04',
                'notes' => 'Need early morning departures for temple visits.',
                'route_summary' => 'Anuradhapura · Sigiriya · Polonnaruwa',
                'trip_story' => 'Heritage-first route with enough time at each site.',
                'trip_highlights' => ['Private guide', 'Lunch stops'],
                'add_ons' => ['Private guide', 'Lunch stops'],
                'itinerary' => ['Anuradhapura', 'Sigiriya climb', 'Polonnaruwa bike tour'],
                'support_contact' => 'operations@lankatrails.example',
                'destination_story' => 'Ancient capitals and sacred sites.',
                'package_story' => 'Heritage cycling and temple visits.',
                'service_story' => 'Guide support for heritage routes.',
                'activity_story' => 'Fortress climb with interpretation.',
            ],
        ];
    }

    public static function normalizeReference(?string $value, int|string|null $fallback = null): string
    {
        $reference = trim((string) $value);
        if ($reference !== '') {
            return $reference;
        }

        return 'TBK-' . now()->format('Y') . '-' . str_pad((string) ($fallback ?? random_int(1, 999999)), 6, '0', STR_PAD_LEFT);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class, 'booking_id');
    }

    public function paymentSummary(): array
    {
        $paymentCount = $this->relationLoaded('payments') ? $this->payments->count() : $this->payments()->count();
        $paidFromPayments = $paymentCount > 0
            ? (int) ($this->relationLoaded('payments') ? $this->payments->sum('amount') : $this->payments()->sum('amount'))
            : (int) ($this->paid_amount ?? 0);
        $totalAmount = (int) ($this->total_amount ?? 0);
        $remaining = max($totalAmount - $paidFromPayments, 0);

        return [
            'paid_amount' => $paidFromPayments,
            'payment_status' => $remaining === 0 && $paidFromPayments > 0
                ? 'paid'
                : ($paidFromPayments > 0 ? 'partial' : 'unpaid'),
            'payment_due_amount' => $remaining,
            'payment_due_date' => optional($this->payment_due_date)->toDateString(),
        ];
    }

    public function syncPaymentSnapshot(): self
    {
        $summary = $this->paymentSummary();

        $this->forceFill([
            'paid_amount' => $summary['paid_amount'],
            'payment_status' => $summary['payment_status'],
        ])->saveQuietly();

        return $this->refresh();
    }

    public function toTourismArray(): array
    {
        $summary = $this->paymentSummary();

        return [
            'id' => $this->id,
            'slug' => $this->reference,
            'title' => $this->customer_name,
            'subtitle' => $this->package_name ?: ($this->destination ?: 'Booking'),
            'description' => $this->notes,
            'status' => $this->booking_status,
            'amount' => $this->total_amount ? 'LKR ' . number_format((int) $this->total_amount) : null,
            'href' => '/customer/bookings/' . $this->reference,
            'fields' => [
                'reference' => $this->reference,
                'customer_name' => $this->customer_name,
                'customer_email' => $this->customer_email,
                'package_name' => $this->package_name,
                'destination' => $this->destination,
                'travel_date' => optional($this->travel_date)->toDateString(),
                'return_date' => optional($this->return_date)->toDateString(),
                'adults' => $this->adults,
                'children' => $this->children,
                'infants' => $this->infants,
                'travelers_count' => $this->travelers_count,
                'total_amount' => $this->total_amount,
                'paid_amount' => $summary['paid_amount'],
                'currency' => $this->currency,
                'booking_status' => $this->booking_status,
                'payment_status' => $summary['payment_status'],
                'payment_due_date' => optional($this->payment_due_date)->toDateString(),
                'route_summary' => $this->route_summary,
                'trip_story' => $this->trip_story,
                'trip_highlights' => $this->trip_highlights ?? [],
                'notes' => $this->notes,
            ],
            'reference' => $this->reference,
            'customer_id' => $this->customer_id,
            'booking_reference' => $this->reference,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'packageName' => $this->package_name,
            'package_name' => $this->package_name,
            'destination' => $this->destination,
            'destination_slug' => $this->destination_slug,
            'package_slug' => $this->package_slug,
            'service_name' => $this->service_name,
            'service_slug' => $this->service_slug,
            'activity_name' => $this->activity_name,
            'activity_slug' => $this->activity_slug,
            'travelDate' => optional($this->travel_date)->toDateString(),
            'travel_date' => optional($this->travel_date)->toDateString(),
            'returnDate' => optional($this->return_date)->toDateString(),
            'return_date' => optional($this->return_date)->toDateString(),
            'travelersCount' => $this->travelers_count,
            'travelers_count' => $this->travelers_count,
            'totalAmount' => $this->total_amount,
            'total_amount' => $this->total_amount,
            'paidAmount' => $summary['paid_amount'],
            'paid_amount' => $summary['paid_amount'],
            'currency' => $this->currency,
            'bookingStatus' => $this->booking_status,
            'status' => $this->booking_status,
            'paymentStatus' => $summary['payment_status'],
            'payment_status' => $summary['payment_status'],
            'paymentDueDate' => optional($this->payment_due_date)->toDateString(),
            'payment_due_date' => optional($this->payment_due_date)->toDateString(),
            'routeSummary' => $this->route_summary,
            'route_summary' => $this->route_summary,
            'tripStory' => $this->trip_story,
            'trip_story' => $this->trip_story,
            'trip_highlights' => $this->trip_highlights ?? [],
            'addOns' => $this->add_ons ?? $this->trip_highlights ?? [],
            'add_ons' => $this->add_ons ?? $this->trip_highlights ?? [],
            'supportContact' => $this->support_contact ?? 'support@lankatrails.example',
            'support_contact' => $this->support_contact ?? 'support@lankatrails.example',
            'itinerary' => $this->itinerary ?? $this->derivedItinerary(),
            'notes' => $this->notes,
            'destination_story' => $this->destination_story,
            'package_story' => $this->package_story,
            'service_story' => $this->service_story,
            'activity_story' => $this->activity_story,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toCustomerSummaryArray(): array
    {
        return $this->toTourismArray();
    }

    private function derivedItinerary(): array
    {
        if (is_array($this->itinerary) && $this->itinerary !== []) {
            return $this->itinerary;
        }

        $summary = trim((string) $this->route_summary);
        if ($summary === '') {
            return [];
        }

        $segments = preg_split('/\s*·\s*/u', $summary) ?: [];
        if ($segments === []) {
            $segments = preg_split('/\s*[|>]\s*/', $summary) ?: [];
        }

        return array_values(array_filter(array_map('trim', $segments)));
    }
}
