<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = $request->input('phone');
        
        // Generate random 4-digit code (Use 1234 for dev if desired, but let's random)
        $code = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // Store in cache for 5 minutes
        $key = 'otp_' . $phone;
        Cache::put($key, $code, 300);

        // Log for development (Mock SMS)
        Log::info("OTP Request for {$phone}: Code is {$code}");

        // TODO: Integrate SMS provider here

        return response()->json(['message' => 'Code sent', 'debug_code' => $code]);
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

        $cachedCode = Cache::get($key);

        if (!$cachedCode || $cachedCode !== $code) {
             throw ValidationException::withMessages([
                 'code' => ['Invalid code provided.'],
             ]);
        }

        // Clear OTP
        Cache::forget($key);

        // Find or Create User
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'Client ' . substr($phone, -4)] 
            // Note: Add other default fields (email, etc.) if required by User model or make them nullable
        );

        // Issue Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
            'role'  => 'client' // Or user->role
        ]);
    }
}
