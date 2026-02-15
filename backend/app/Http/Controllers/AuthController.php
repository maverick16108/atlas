<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Admin/Moderator Login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Super Admin bypass: check .env credentials first
        $superAdminEmail = env('SUPER_ADMIN_EMAIL');
        $superAdminPassword = env('SUPER_ADMIN_PASSWORD');

        if ($superAdminEmail && $superAdminPassword && 
            $email === $superAdminEmail && $password === $superAdminPassword) {
            
            // Find or create super admin user
            $user = User::firstOrCreate(
                ['email' => $superAdminEmail],
                [
                    'name' => 'Super Admin',
                    'password' => Hash::make($superAdminPassword),
                    'role' => 'super_admin',
                    'phone' => 'super_admin_' . uniqid(),
                    'is_accredited' => true,
                ]
            );

            // Ensure role is super_admin and update last login
            $user->role = 'super_admin';
            $user->last_login_at = now();
            $user->save();

            return response()->json([
                'token' => $user->createToken('super_admin_token')->plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=dc2626&color=fff'
                ],
                'role' => $user->role
            ]);
        }

        // Regular user authentication from database
        $user = User::where('email', $email)->first();

        // Check password
        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        // Check Role
        if (!in_array($user->role, ['admin', 'super_admin', 'moderator'])) {
            throw ValidationException::withMessages([
                'email' => ['Блокировано: Отсутствуют права доступа.'],
            ]);
        }

        // Update last login timestamp
        $user->last_login_at = now();
        $user->save();

        // Return token
        return response()->json([
            'token' => $user->createToken('admin_token')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&background=random&color=fff'
            ],
            'role' => $user->role
        ]);
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $request->input('phone');
        
        // Find user by AUTH phone and check accreditation
        $user = User::where('auth_phone', $phone)->where('role', 'client')->first();

        if (!$user) {
             return response()->json(['message' => 'User not found or phone not registered for auth.'], 404);
        }

        if (!$user->is_accredited) {
             return response()->json(['message' => 'Account is not accredited.'], 403);
        }

        // Development Bypass: Always return success with 0000
        return response()->json(['message' => 'Code sent', 'debug_code' => '0000']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string|size:4',
        ]);

        $phone = $request->input('phone');
        $code  = $request->input('code');
        $key   = 'otp_' . $phone;

        // SMS Service Bypass: Allow 0000 for any user while SMS is not connected
        if ($code !== '0000') {
            $cachedCode = Cache::get($key);

            if (!$cachedCode || $cachedCode !== $code) {
                 throw ValidationException::withMessages([
                     'code' => ['Invalid code provided.'],
                 ]);
            }
            Cache::forget($key);
        }

        // Find User by Auth Phone
        $user = User::where('auth_phone', $phone)->where('role', 'client')->first();

        if (!$user || !$user->is_accredited) {
            return response()->json(['message' => 'Authentication failed or revoked.'], 403);
        }

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Issue Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
            'role'  => 'client'
        ]);
    }

    /**
     * Register a new user (Accreditation Request).
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
             'name' => 'required|string|max:255',
             'email' => 'required|email|max:255',
             'phone' => 'required|string|max:20',
        ]);

        // Check if user already exists by email or phone (CONTACT details)
        $user = User::where('email', $validated['email'])
                    ->orWhere('phone', $validated['phone'])
                    ->first();

        if (!$user) {
            $user = new User();
            $user->password = Hash::make(Str::random(32)); 
            $user->role = 'client';
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone']; // Contact phone
        $user->auth_phone = null; // Admin must set this manually
        $user->is_accredited = false; 
        
        $user->save();

        return response()->json(['message' => 'Application submitted successfully']);
    }
}
