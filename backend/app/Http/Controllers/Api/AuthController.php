<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        if ($user->is_locked) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been locked. Contact administrator.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Login',
            'description' => $user->name.' signed into the system.',
        ]);

        // Remember me: 30-day token; otherwise a short 12-hour token, so a shared/kiosk
        // login at a site doesn't stay valid indefinitely.
        $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addHours(12);
        $token = $user->createToken('spa', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Logout',
            'description' => $user->name.' signed out of the system.',
        ]);

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('roles:id,name,slug,is_active'));
    }

    /**
     * The signed-in account's own permission set, used by the SPA to decide
     * which menu entries and buttons to render.
     *
     * This is presentation only. Every route is independently guarded server
     * side — hiding a button here never replaces that check, it just avoids
     * offering an action that would come back 403.
     */
    public function permissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'role' => $user->role,
            'permissions' => $user->effectivePermissions(),
            'hidden_modules' => $user->hiddenModules(),
            'roles' => $user->roles()->get(['roles.id', 'name', 'slug', 'is_active'])->all(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'receive_reports' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $request->name,
            'phone' => $request->phone,
        ];

        // Personal opt-out of the scheduled report email — only meaningful
        // for staff (see SendScheduledAssetReport), but harmless to store
        // for any role.
        if ($request->has('receive_reports')) {
            $data['receive_reports'] = $request->boolean('receive_reports');
        }

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
            'description' => $user->name.' updated their profile.',
        ]);

        return response()->json($user->fresh());
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Password Change',
            'description' => $user->name.' changed their password.',
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
