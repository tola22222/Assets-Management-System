<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use App\Models\ActivityLog;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
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

        $user = User::where('email', $request->email)->first();

        if ($user && !$user->is_active) {
            return back()->withErrors(['email' => 'Your account has been deactivated.'])->onlyInput('email');
        }

        if ($user && $user->is_locked) {
            return back()->withErrors(['email' => 'Your account has been locked. Contact administrator.'])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $user->update(['last_login_at' => now()]);

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

    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Password Change',
            'description' => $user->name . ' changed their password.',
        ]);

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    public function showProfile()
    {
        $user = Auth::user()->load(['staff', 'unreadNotifications']);
        return view('profile.index', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
        ];

        if ($request->hasFile('photo')) {
            if ($user->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('profiles', 'public');
        }

        $user->update($data);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Profile Update',
            'description' => $user->name . ' updated their profile.',
        ]);

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }
}
