<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnonymousSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pixels_placed' => $user->pixels_placed,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $validated['email'])->first();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pixels_placed' => $user->pixels_placed,
            ],
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function anonymous(Request $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $session = AnonymousSession::createWithToken($ipAddress);

        return response()->json([
            'token' => $session->token,
            'pixels_placed' => $session->pixels_placed,
        ])->withCookie(cookie('session_token', $session->token, 60 * 24 * 30)); // 30 days
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            return response()->json([
                'type' => 'user',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pixels_placed' => $user->pixels_placed,
            ]);
        }

        // Check for anonymous session
        $token = $request->header('X-Session-Token')
            ?? $request->cookie('session_token');

        if ($token) {
            $session = AnonymousSession::findByToken($token);
            if ($session) {
                return response()->json([
                    'type' => 'anonymous',
                    'pixels_placed' => $session->pixels_placed,
                ]);
            }
        }

        return response()->json([
            'type' => 'none',
        ], 401);
    }
}
