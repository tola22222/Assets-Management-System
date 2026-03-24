<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required', // Useful for revoking specific mobile tokens
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        // Create Token
        $token = $user->createToken($request->device_name)->plainTextToken;

        // Record Activity
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Login (API)',
            'description' => $user->name . ' signed into the mobile app.',
        ]);

        return response()->json([
            'status' => true,
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        // Record Activity before deleting token
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Logout (API)',
            'description' => $request->user()->name . ' signed out from mobile.',
        ]);

        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => true, 'message' => 'Logged out successfully']);
    }
}