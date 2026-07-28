<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetReturn;
use App\Models\AssetAssignment;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetReturnController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->isOperationsHrManager()) {
            $returns = AssetReturn::with(['asset', 'assignment', 'returnedBy'])
                ->latest()
                ->get();
        } else {
            $returns = AssetReturn::where('returned_by', $user->id)
                ->with(['asset', 'assignment'])
                ->latest()
                ->get();
        }
        $assignments = AssetAssignment::where('status', 'active')->with('asset', 'assignee')->get();
        return view('asset-returns.index', compact('returns', 'assignments'));
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

        Notification::create([
            'user_id' => User::where('role', 'operations_hr_manager')->first()->id,
            'type' => 'return_request',
            'message' => 'New asset return request submitted.',
            'url' => route('asset-returns.index'),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Return Request',
            'description' => 'Requested return of asset',
        ]);

        return redirect()->route('asset-returns.index')->with('success', 'Return request submitted successfully.');
    }

    public function approve(AssetReturn $return)
    {
        DB::transaction(function () use ($return) {
            $return->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
            ]);

            $return->assignment->update(['status' => 'returned']);
        });

        Notification::create([
            'user_id' => $return->returned_by,
            'type' => 'return_approved',
            'message' => 'Your return request has been approved.',
            'url' => route('asset-returns.index'),
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Approve',
            'description' => 'Approved asset return',
        ]);

        return redirect()->route('asset-returns.index')->with('success', 'Return approved successfully.');
    }

    public function reject(Request $request, AssetReturn $return)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string',
        ]);

        $return->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return redirect()->route('asset-returns.index')->with('success', 'Return rejected.');
    }

    public function show(AssetReturn $return)
    {
        $return->load(['asset', 'assignment', 'returnedBy', 'approvedBy']);
        return view('asset-returns.show', compact('return'));
    }
}
