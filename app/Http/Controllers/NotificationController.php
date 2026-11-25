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

    public function feed(Request $request)
    {
        $items = $request->user()->unreadNotifications()->latest()->limit(8)->get();

        return response()->json([
            'count' => $request->user()->unreadNotifications()->count(),
            'items' => $items->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => (string) ($notification->data['title'] ?? ''),
                'body' => (string) ($notification->data['body'] ?? ''),
                'read_url' => route('notifications.read', $notification->id),
            ])->values(),
        ]);
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
