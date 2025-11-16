<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        try {
            $path = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('profile-photos', 'public');
            }

            $user = DB::transaction(function () use ($validated, $path) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($validated['password']),
                    'image' => $path,
                ]);

                $userRole = Role::where('name', 'user')->first();
                if ($userRole) {
                    $user->roles()->attach($userRole);
                }
                return $user;
            });

            Auth::login($user);
            $request->session()->regenerate();

            // Get user role
            $userRole = $user->roles->first()?->name ?? 'user';

            // Set cookies (akan di-exclude dari enkripsi)
            return response()
                ->json(new UserResource($user->load('roles')), 201)
                ->cookie('user_role', $userRole, 60 * 24 * 7, '/', null, true, false, false, 'lax')
                ->cookie('isLoggedIn', 'true', 60 * 24 * 7, '/', null, true, false, false, 'lax');
        } catch (\Throwable $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during registration.'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Email atau password yang Anda masukkan salah',
                ], 401);
            }

            $request->session()->regenerate();
            $user = Auth::user();
            /** @var \App\Models\User $user */
            $user->load('roles');

            // Get user role
            $userRole = $user->roles->first()?->name ?? 'user';

            // Set cookies (tidak akan di-encrypt karena sudah di-exclude)
            return response()
                ->json(new UserResource($user))
                ->cookie('user_role', $userRole, 60 * 24 * 7, '/', null, true, false, false, 'lax')
                ->cookie('isLoggedIn', 'true', 60 * 24 * 7, '/', null, true, false, false, 'lax');
        } catch (\Throwable $e) {
            Log::error('Login failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat login. Silakan coba lagi.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()
                ->json(['message' => 'Successfully logged out'])
                ->withCookie(Cookie::forget('user_role'))
                ->withCookie(Cookie::forget('isLoggedIn'))
                ->withCookie(Cookie::forget(config('session.cookie')));
        } catch (\Throwable $e) {
            Log::error('Logout failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred during logout.'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /** @var \App\Models\User $user */
            return response()->json(new UserResource($user->load('roles')));
        } catch (\Throwable $e) {
            Log::error('Get user failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred'
            ], 500);
        }
    }
}
