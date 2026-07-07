<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSession;
use App\Support\SessionKiller;
use App\Support\SessionTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Jenssegers\Agent\Agent;

class AuthController extends Controller
{
    // Define max device count for single user login sessions
    private int $maxDeviceCount = 4;
    /**
     * Cookie-based signup (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'tenantKey' => ['nullable', 'string', 'max:100'],
            'device_name'  => ['nullable', 'string', 'max:120'],
            'browser_name' => ['nullable', 'string', 'max:40'],
            'os_name'      => ['nullable', 'string', 'max:40'],
            'device_type'  => ['nullable', 'in:desktop,mobile,tablet'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            if (!empty($validated['tenantKey'])) {
                $tenant = Tenant::query()->where('key', $validated['tenantKey'])->first();
                if (!$tenant) {
                    throw ValidationException::withMessages([
                        'tenantKey' => ['Unknown tenant.'],
                    ]);
                }

                $tenant->users()->syncWithoutDetaching([
                    $user->id => [
                        'role' => 'customer',
                        'status' => 'active',
                        'joined_at' => now(),
                        'invited_by_user_id' => $user->id,
                    ],
                ]);

                return $user;
            }

            // Create initial tenant with random 8-char key/name
            $tenant = Tenant::create([
                'name' => $this->uniqueTenantCode(8),
                'key' => $this->uniqueTenantCode(8),
                'created_by_user_id' => $user->id,
            ]);

            $tenant->trial_ends_at = now()->addMonthsNoOverflow(6);
            $tenant->save();

            // Attach user to tenant
            $tenant->users()->attach($user->id, [
                'role' => 'tenant_owner',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => $user->id,
            ]);

            return $user;
        });

        // Log in using the session (cookie)
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $sid = $request->session()->getId();

        // Extract device details from agent
        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $browser = $request->input('browser_name') ?: $agent->browser();
        $os      = $request->input('os_name') ?: $agent->platform();

        $deviceType = $request->input('device_type')
            ?: ($agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : 'desktop'));

        $deviceName = $request->input('device_name')
            ?: "{$browser} on {$os}";

        // Track current session every time you call me (your request)
        UserSession::updateOrCreate(
            ['user_id' => $user->id, 'session_id' => $sid],
            [
                'os' => $os,
                'browser' => $browser,
                'device_type' => $deviceType,
                'device_name' => $deviceName,
                'user_agent' => $request->userAgent(),
                'ip_last' => $request->ip(),
                'last_seen_at' => now(),
                'expires_at' => now()->addMinutes((int) config('session.lifetime')),
            ]
        );

        $user->forceFill([
            'current_session_id' => $sid,
            'current_session_set_at' => now(),
        ])->save();

        return response()->json([
            'ok'   => true,
            'status' => 201,
            'user' => UserResource::make($request->user()->load('tenants')),
            'message' => 'User account created successfully.'
        ], 201);
    }

    /**
     * Cookie-based signin (session auth).
     * Requires valid XSRF token (419 if missing/invalid).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'device_name'  => ['nullable', 'string', 'max:120'],
            'browser_name' => ['nullable', 'string', 'max:40'],
            'os_name'      => ['nullable', 'string', 'max:40'],
            'device_type'  => ['nullable', 'in:desktop,mobile,tablet'],
        ]);

        $remember = (bool)($credentials['remember'] ?? false);

        if (! Auth::guard('web')->attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            return response()->json([
                'ok'     => false,
                'errors' => ['email' => ['Invalid credentials.']],
            ], 422);
        }

        // Prevent session fixation
        $request->session()->regenerate();
        $sid = $request->session()->getId();

        /** @var User $user */
        $user = $request->user();
        SessionTracker::track($request, $user->id, 'login');

        DB::transaction(function () use ($request, $user, $sid) {
            // Extract device details from agent
            $agent = new Agent();
            $agent->setUserAgent($request->userAgent());

            $browser = $request->input('browser_name') ?: $agent->browser();
            $os      = $request->input('os_name') ?: $agent->platform();

            $deviceType = $request->input('device_type')
                ?: ($agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : 'desktop'));

            $deviceName = $request->input('device_name')
                ?: "{$browser} on {$os}";

            // Track current session every time you call me (your request)
            UserSession::updateOrCreate(
                ['user_id' => $user->id, 'session_id' => $sid],
                [
                    'os' => $os,
                    'browser' => $browser,
                    'device_type' => $deviceType,
                    'device_name' => $deviceName,
                    'user_agent' => $request->userAgent(),
                    'ip_last' => $request->ip(),
                    'last_seen_at' => now(),
                    'expires_at' => now()->addMinutes((int) config('session.lifetime')),
                ]
            );

            // Override current session pointer in user table
            $user->forceFill([
                'current_session_id' => $sid,
                'current_session_set_at' => now()
            ])->save();

            // Enforce max devices (kick oldest)
            $active = UserSession::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('last_seen_at')
                ->orderByDesc('created_at')
                ->get();

            if ($active->count() > $this->maxDeviceCount) {
                $overflow = $active->slice($this->maxDeviceCount);

                foreach ($overflow as $old) {
                    $old->update([
                        'revoked_at' => now(),
                        'revoke_reason' => 'max devices reached.'
                    ]);

                    SessionKiller::kill($old->session_id);
                }
            }
        });

        return response()->json([
            'ok'   => true,
            'status' => 200,
            'user' => UserResource::make($request->user()->load('tenants')),
            'message' => 'User login successfully.'
        ], 200);
    }

    /**
     * Logout (session cookie).
     * Requires valid XSRF token (POST).
     */
    public function logout(Request $request)
    {
        $sid = $request->session()->getId();

        UserSession::where('user_id', $request->user()->id)
            ->where('session_id', $sid)
            ->update([
                'revoked_at' => now(),
                'revoke_reason' => 'logout'
            ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        SessionKiller::kill($sid);

        return response()->noContent();
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'device_name'  => ['nullable', 'string', 'max:120'],
            'browser_name' => ['nullable', 'string', 'max:40'],
            'os_name'      => ['nullable', 'string', 'max:40'],
            'device_type'  => ['nullable', 'in:desktop,mobile,tablet'],
        ]);

        $sid = $request->hasSession() ? $request->session()->getId() : null;

        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        $browser = $request->input('browser_name') ?: $agent->browser();
        $os      = $request->input('os_name') ?: $agent->platform();

        $deviceType = $request->input('device_type')
            ?: ($agent->isTablet() ? 'tablet' : ($agent->isMobile() ? 'mobile' : 'desktop'));

        $deviceName = $request->input('device_name')
            ?: "{$browser} on {$os}";

        if ($sid) {
            UserSession::updateOrCreate(
                ['user_id' => $user->id, 'session_id' => $sid],
                [
                    'os' => $os,
                    'browser' => $browser,
                    'device_type' => $deviceType,
                    'device_name' => $deviceName,
                    'user_agent' => $request->userAgent(),
                    'ip_last' => $request->ip(),
                    'last_seen_at' => now(),
                    'expires_at' => now()->addMinutes((int) config('session.lifetime')),
                ]
            );

            $user->forceFill([
                'current_session_id' => $sid,
                'current_session_set_at' => now(),
            ])->save();
        }

        return response()->json([
            'ok'   => true,
            'user' => UserResource::make($user->load('tenants')),
        ]);
    }

    /**
     * Generate a unique random tenant code
     */
    private function uniqueTenantCode(int $length = 8): string
    {
        do {
            $code = Str::lower(Str::random($length));
        } while (
            Tenant::query()->where('key', $code)->orWhere('name', $code)->exists()
        );

        return $code;
    }
}
