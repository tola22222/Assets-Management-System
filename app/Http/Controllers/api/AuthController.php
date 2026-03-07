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
                    'message' => 'Login successful',
                    'user' => Auth::user(),
                ], 200);
            }
            return response()->json([
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        } catch (\Throwable $th) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }
    }

    public function logout(Request $request)
    {
        // Optional: Record logout activity before clearing session
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Logout',
            'description' => Auth::user()->name . ' signed out.',
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
