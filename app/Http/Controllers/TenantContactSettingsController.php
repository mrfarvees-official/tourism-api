<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TenantContactSettingsController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum'),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        return response()->json([
            'ok' => true,
            'status' => 200,
            'settings' => $this->normalizeSettings($tenant->setting?->settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'email' => ['required', 'email', 'max:190'],
            'google_app_key' => ['required', 'string', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:190'],
            'sender_name' => ['nullable', 'string', 'max:190'],
            'payment_provider' => ['nullable', 'string', 'max:80'],
            'payment_business_email' => ['nullable', 'email', 'max:190'],
            'payment_client_id' => ['nullable', 'string', 'max:255'],
            'payment_client_secret' => ['nullable', 'string', 'max:255'],
            'payment_currency' => ['nullable', 'string', 'max:8'],
            'payment_brand_name' => ['nullable', 'string', 'max:190'],
            'payment_partial_amount' => ['nullable', 'string', 'max:64'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = $this->normalizeSettings([
            'email' => $validated['email'],
            'google_app_key' => $validated['google_app_key'],
            'reply_to_email' => $validated['reply_to_email'] ?? null,
            'sender_name' => $validated['sender_name'] ?? null,
            'payment_provider' => $validated['payment_provider'] ?? null,
            'payment_business_email' => $validated['payment_business_email'] ?? null,
            'payment_client_id' => $validated['payment_client_id'] ?? null,
            'payment_client_secret' => $validated['payment_client_secret'] ?? null,
            'payment_currency' => $validated['payment_currency'] ?? null,
            'payment_brand_name' => $validated['payment_brand_name'] ?? null,
            'payment_partial_amount' => $validated['payment_partial_amount'] ?? null,
            'payment_note' => $validated['payment_note'] ?? null,
        ]);

        TenantSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['settings' => $settings]
        );

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Contact settings updated.',
            'settings' => $settings,
        ]);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenantKey = $request->input('tenantKey');
        $tenant = Tenant::query()->where('key', $tenantKey)->first();

        abort_if(!$tenant, 404, 'Unknown tenant');

        return $tenant;
    }

    private function assertTenantUser(Request $request, Tenant $tenant): void
    {
        $user = $request->user();

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        abort_if(!$tenantUser, 404, 'Unknown tenant user');
    }

    private function normalizeSettings(mixed $settings): array
    {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'email' => trim((string) ($settings['email'] ?? '')),
            'google_app_key' => trim((string) ($settings['google_app_key'] ?? '')),
            'reply_to_email' => trim((string) ($settings['reply_to_email'] ?? '')),
            'sender_name' => trim((string) ($settings['sender_name'] ?? '')),
            'payment_provider' => trim((string) ($settings['payment_provider'] ?? 'paypal_sandbox')),
            'payment_business_email' => trim((string) ($settings['payment_business_email'] ?? '')),
            'payment_client_id' => trim((string) ($settings['payment_client_id'] ?? '')),
            'payment_client_secret' => trim((string) ($settings['payment_client_secret'] ?? '')),
            'payment_currency' => trim((string) ($settings['payment_currency'] ?? 'LKR')),
            'payment_brand_name' => trim((string) ($settings['payment_brand_name'] ?? '')),
            'payment_partial_amount' => trim((string) ($settings['payment_partial_amount'] ?? '100')),
            'payment_note' => trim((string) ($settings['payment_note'] ?? '')),
        ];
    }
}
