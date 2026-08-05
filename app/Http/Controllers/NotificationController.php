<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List the current user's notifications.
     */
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
