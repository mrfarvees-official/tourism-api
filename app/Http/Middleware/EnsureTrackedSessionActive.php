<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Support\SessionTracker;
use Closure;
use Illuminate\Http\Request;

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

        return $next($request);
    }
}
