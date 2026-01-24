<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Redirect to dashboard if user is already logged in
        if (Auth::check()) {
            return redirect()->intended('/');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Record Login Activity
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Login',
                'description' => Auth::user()->name . ' signed into the system.',
            ]);

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
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