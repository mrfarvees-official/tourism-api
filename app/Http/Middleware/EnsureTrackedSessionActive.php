<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantActivityLog;
use App\Models\TenantUser;
use App\Models\UserSession;
use App\Support\SessionTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsureTrackedSessionActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $sid = $request->session()->getId();

        $row = UserSession::where('user_id', $user->id)
            ->where('session_id', $sid)
            ->first();

        // Auto-track if missing (first request after login / session rotation)
        if (!$row) {
            $row = SessionTracker::track($request, $user->id, 'auto_track');
        }

        if ($row->revoked_at || ($row->expires_at && $row->expires_at->isPast())) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return response()->json(['message' => 'Session revoked/expired'], 401);
        }

        // Throttle updates (optional)
        $shouldUpdate = !$row->last_seen_at || $row->last_seen_at->lte(now()->subSeconds(60));
        if ($shouldUpdate) {
            $row->forceFill([
                'last_seen_at' => now(),
                'ip_last' => $request->ip(),
                'expires_at' => now()->addMinutes((int) config('session.lifetime')),
            ])->save();
        }

        $response = $next($request);

        if ($this->shouldCaptureActivity($request, $response->getStatusCode())) {
            $this->captureActivity($request, $user->id);
        }

        return $response;
    }

    private function shouldCaptureActivity(Request $request, int $statusCode): bool
    {
        if ($statusCode >= 400) {
            return false;
        }

        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function captureActivity(Request $request, int $userId): void
    {
        $tenantKey = (string) ($request->input('tenantKey') ?? $request->query('tenantKey') ?? '');
        if ($tenantKey === '') {
            return;
        }

        $tenant = Tenant::query()->where('key', $tenantKey)->first();
        if (!$tenant) {
            return;
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return;
        }

        $resource = $this->resolveActivityResource($request);
        $resource = Str::headline(str_replace(['-', '_'], ' ', (string) $resource));
        $verb = match ($request->method()) {
            'POST' => 'Created',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => 'Changed',
        };

        TenantActivityLog::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'action' => strtolower($request->method()),
            'label' => trim($verb . ' ' . $resource),
            'summary' => trim(sprintf(
                '%s %s via %s',
                $verb,
                strtolower($resource),
                $request->route()?->uri() ?? $request->path(),
            )),
            'subject_type' => $resource,
            'route' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'meta' => [
                'route_name' => $request->route()?->getName(),
                'query' => $request->query(),
            ],
        ]);
    }

    private function resolveActivityResource(Request $request): string
    {
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/'))));

        foreach (['admin', 'company', 'tenant'] as $anchor) {
            $index = array_search($anchor, $segments, true);
            if ($index !== false && isset($segments[$index + 1])) {
                return (string) $segments[$index + 1];
            }
        }

        return (string) (end($segments) ?: 'activity');
    }
}
