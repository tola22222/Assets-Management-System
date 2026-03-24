<?php

namespace App\Http\Controllers\api;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if (Auth::attempt($credentials, $request->boolean('remember'))) {

                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Login',
                    'description' => Auth::user()->name . ' signed into the system.',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => Auth::user(),
                ], 200);
            }
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $th->getMessage(),
            ], 422);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Record logout activity
            if (Auth::check()) {
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Logout',
                    'description' => Auth::user()->name . ' signed out.',
                ]);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}