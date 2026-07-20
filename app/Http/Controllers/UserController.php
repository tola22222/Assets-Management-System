<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('staff')->latest()->get();
        $staffList = Staff::where('status', 'active')->get();

        return view('users.index', compact('users', 'staffList'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:operations_hr_manager,staff,executive_director,finance_manager',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Create',
            'description' => 'Created user: '.$user->name,
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        return response()->json($user->load('staff'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:operations_hr_manager,staff,executive_director,finance_manager',
            'staff_id' => 'nullable|exists:staff,id',
        ]);

        $user->update($validated);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => 'Updated user: '.$user->name,
        ]);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function lock(User $user)
    {
        $user->update(['is_locked' => ! $user->is_locked]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Update',
            'description' => ($user->is_locked ? 'Locked' : 'Unlocked').' user: '.$user->name,
        ]);

        return redirect()->route('users.index')->with('success', 'User '.($user->is_locked ? 'locked' : 'unlocked').' successfully.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Password Reset',
            'description' => 'Reset password for user: '.$user->name,
        ]);

        return redirect()->route('users.index')->with('success', 'Password reset successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Delete',
            'description' => 'Deleted user: '.$user->name,
        ]);

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
