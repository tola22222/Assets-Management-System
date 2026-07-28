<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        return response()->json(
            Notification::where('user_id', Auth::id())->latest()->paginate(20)
        );
    }

    public function markAsRead(Notification $notification)
    {
        $notification->update(['is_read' => true]);
        return response()->json($notification->fresh());
    }

    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function unreadCount()
    {
        return response()->json([
            'count' => Notification::where('user_id', Auth::id())->where('is_read', false)->count(),
        ]);
    }
}
