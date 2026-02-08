<?php

namespace App\Support;

use App\Models\UserSession;

class SessionKiller
{
    public static function kill(string $sessionId): void
    {
        // Always revoke in DB so middleware blocks it (works for cookie sessions)
        UserSession::where('session_id', $sessionId)->update([
            'revoked_at' => now(),
        ]);

        // Optional extra cleanup if using file sessions
        if (config('session.driver') === 'file') {
            $path = rtrim(config('session.files'), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'sess_' . $sessionId;

            @unlink($path);
        }
    }

    public static function killOthers(int $userId, string $currentSessionId): void
    {
        UserSession::where('user_id', $userId)
            ->where('session_id', '!=', $currentSessionId)
            ->update(['revoked_at' => now()]);
    }
}
