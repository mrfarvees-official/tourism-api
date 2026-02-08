<?php

namespace App\Support;

use App\Models\UserSession;
use Illuminate\Http\Request;

class SessionTracker
{
    public static function track(Request $request, int $userId, ?string $reason = null): UserSession
    {
        $sid = $request->session()->getId();

        $row = UserSession::firstOrCreate(
            ['user_id' => $userId, 'session_id' => $sid],
            [
                'user_agent' => $request->userAgent(),
                'ip_first' => $request->ip(),
            ]
        );

        $row->forceFill([
            'user_agent' => $request->userAgent(),
            'ip_last' => $request->ip(),
            'last_seen_at' => now(),
            'expires_at' => now()->addMinutes((int) config('session.lifetime')),
        ])->save();

        // override pointer in users table
        $request->user()?->forceFill([
            'current_session_id' => $sid,
            'current_session_set_at' => now(),
        ])->save();

        return $row;
    }
}