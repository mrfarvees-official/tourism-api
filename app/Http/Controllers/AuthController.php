<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
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
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Log in using the session (cookie)
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
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

        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Logout (session cookie).
     * Requires valid XSRF token (POST).
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
        ]);
    }
}
