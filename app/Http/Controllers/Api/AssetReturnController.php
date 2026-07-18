<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssetReturn;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetReturnController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $returns = AssetReturn::with(['asset', 'assignment', 'returnedBy'])->latest()->get();
        } else {
            $returns = AssetReturn::where('returned_by', $user->id)->with(['asset', 'assignment'])->latest()->get();
        }

        return response()->json($returns);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assignment_id' => 'required|exists:asset_assignments,id',
            'asset_id' => 'required|exists:assets,id',
            'condition' => 'required|string',
            'damage_notes' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'return_date' => 'required|date',
        ]);

        $validated['returned_by'] = Auth::id();
        $validated['status'] = 'pending';

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')->store('returns', 'public');
        }

        $return = AssetReturn::create($validated);

        $admin = User::where('role', 'admin')->first();
        if ($admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'return_request',
                'message' => 'New asset return request submitted.',
                'url' => null,
            ]);
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Return Request',
            'description' => 'Requested return of asset',
        ]);

        return response()->json($return->fresh(['asset', 'assignment']), 201);
    }

    public function approve(AssetReturn $asset_return)
    {
        DB::transaction(function () use ($asset_return) {
            $asset_return->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);

            $asset_return->assignment->update(['status' => 'returned']);
        });

        Notification::create([
            'user_id' => $asset_return->returned_by,
            'type' => 'return_approved',
            'message' => 'Your return request has been approved.',
            'url' => null,
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved asset return',
        ]);

        return response()->json($asset_return->fresh());
    }

    public function reject(Request $request, AssetReturn $asset_return)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $asset_return->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return response()->json($asset_return->fresh());
    }
}
