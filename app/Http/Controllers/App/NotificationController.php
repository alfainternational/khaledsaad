<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(50)->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'إشعار',
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read' => $notification->read_at !== null,
                'at' => $notification->created_at->diffForHumans(),
            ]);

        // نفس الحمولة للجرس (JSON) وللصفحة الكاملة (Blade).
        if ($request->wantsJson()) {
            return response()->json([
                'data' => $notifications,
                'unread' => $user->unreadNotifications()->count(),
            ]);
        }

        return view('app.notifications.index', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $target = $notification->data['url'] ?? route('app.notifications.index');

        return $request->wantsJson()
            ? response()->json(['data' => ['url' => $target]])
            : redirect()->to($target);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'عُلّمت كل الإشعارات كمقروءة.');
    }
}
