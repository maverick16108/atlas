<?php

namespace App\Http\Controllers;

use App\Models\ClientNotification;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    /**
     * Get notifications for the current admin/moderator user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = ClientNotification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'auction_id' => $n->auction_id,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return response()->json($notifications);
    }

    /**
     * Count of unread notifications.
     */
    public function unreadCount(Request $request)
    {
        $count = ClientNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = ClientNotification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        ClientNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
