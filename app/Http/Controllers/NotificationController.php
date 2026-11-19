<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('ui.saved'));
    }
}
