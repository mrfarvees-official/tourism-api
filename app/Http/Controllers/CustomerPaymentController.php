<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CustomerPayment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerPaymentController extends Controller
{
    private function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => $status,
            'data' => $data,
        ], $status);
    }

    private function error(string $message, int $status = 404): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => $status,
            'error' => $message,
        ], $status);
    }

    private function tenant(string $tenantKey): ?Tenant
    {
        return Tenant::query()->where('key', $tenantKey)->first();
    }

    private function customerBookingRecord(string $tenantKey, string $bookingReference): ?Booking
    {
        $tenant = $this->tenant($tenantKey);
        if (!$tenant) {
            return null;
        }

        return Booking::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($bookingReference) {
                $query->where('reference', $bookingReference)
                    ->orWhere('id', $bookingReference);
            })
            ->first();
    }

    private function syncBookingPaymentSnapshot(Booking $booking): Booking
    {
        $summary = $booking->paymentSummary();

        $booking->forceFill([
            'paid_amount' => $summary['paid_amount'],
            'payment_status' => $summary['payment_status'],
            'payment_due_date' => $summary['payment_due_date'] ?? $booking->payment_due_date,
        ])->saveQuietly();

        return $booking->refresh();
    }

    private function recordPayment(Booking $booking, array $validated, string $source): array
    {
        $amount = max(0, (int) ($validated['amount'] ?? 0));
        if ($amount <= 0) {
            abort(422, 'Payment amount must be greater than zero.');
        }

        $payment = CustomerPayment::query()->create([
            'tenant_id' => $booking->tenant_id,
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference,
            'customer_id' => $booking->customer_id,
            'amount' => $amount,
            'currency' => trim((string) ($validated['currency'] ?? $booking->currency ?? 'LKR')) ?: 'LKR',
            'payment_method' => trim((string) ($validated['payment_method'] ?? 'card')) ?: 'card',
            'payment_brand' => trim((string) ($validated['payment_brand'] ?? '')) ?: null,
            'card_last4' => trim((string) ($validated['card_last4'] ?? '')) ?: null,
            'card_holder_name' => trim((string) ($validated['card_holder_name'] ?? '')) ?: null,
            'status' => trim((string) ($validated['status'] ?? 'paid')) ?: 'paid',
            'provider_reference' => trim((string) ($validated['provider_reference'] ?? '')) ?: null,
            'payment_payload' => $validated,
            'meta' => [
                'source' => $source,
                'paid_at' => now()->toIso8601String(),
            ],
            'paid_at' => Carbon::now(),
        ]);

        $booking = $this->syncBookingPaymentSnapshot($booking);

        return [$payment, $booking];
    }

    public function storeCustomer(Request $request, string $bookingReference): JsonResponse
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_brand' => ['nullable', 'string', 'max:50'],
            'card_last4' => ['nullable', 'string', 'max:4'],
            'card_holder_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = $this->customerBookingRecord((string) $validated['tenantKey'], $bookingReference);
        if (!$booking) {
            return $this->error('Record not found.');
        }

        [$_payment, $booking] = $this->recordPayment($booking, $validated, 'customer-portal');

        return $this->ok($booking->toCustomerSummaryArray());
    }

    public function storePublic(Request $request, string $tenantKey, string $bookingReference): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_brand' => ['nullable', 'string', 'max:50'],
            'card_last4' => ['nullable', 'string', 'max:4'],
            'card_holder_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $booking = $this->customerBookingRecord($tenantKey, $bookingReference);
        if (!$booking) {
            return $this->error('Record not found.');
        }

        [$_payment, $booking] = $this->recordPayment($booking, array_merge($validated, ['tenantKey' => $tenantKey]), 'public-booking');

        return $this->ok($booking->toTourismArray());
    }
}
