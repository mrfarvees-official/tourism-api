<?php

namespace App\Http\Controllers;

use App\Models\UserSession;
use App\Support\SessionKiller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceSessionController extends Controller
{
    public function list(Request $request)
    {
        $userId = $request->user()->id;
        $currentSid = $request->session()->getId();

        $sessions = UserSession::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'device_name' => $s->device_name,
                'device_type' => $s->device_type,
                'os' => $s->os,
                'browser' => $s->browser,
                'ip_last' => $s->ip_last,
                'user_agent' => $s->user_agent,
                'created_at' => $s->created_at,
                'last_seen_at' => $s->last_seen_at,
                'expires_at' => $s->expires_at,
                'is_current' => $s->session_id === $currentSid,
            ]);

        return response()->json([
            'current_session_id' => $currentSid,
            'sessions' => $sessions,
        ]);
    }

    public function logoutSingle(Request $request, string $id)
    {
        $userId = $request->user()->id;
        $currentSid = $request->session()->getId();

        $session = UserSession::where('user_id', $userId)->where('id', $id)->firstOrFail();

        $session->update([
            'revoked_at' => now(),
            'revoke_reason' => 'manual_single',
        ]);

        // Optional: kill remote session file
        SessionKiller::kill($session->session_id);

        // If they logged out current session, invalidate immediately
        if ($session->session_id === $currentSid) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['ok' => true]);
    }

    public function logoutOthers(Request $request)
    {
        $userId = $request->user()->id;
        $currentSid = $request->session()->getId();

        $others = UserSession::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('session_id', '!=', $currentSid)
            ->get();

        foreach ($others as $s) {
            $s->update([
                'revoked_at' => now(),
                'revoke_reason' => 'manual_others',
            ]);
            SessionKiller::kill($s->session_id);
        }

        return response()->json(['ok' => true]);
    }
}