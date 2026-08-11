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
                'title' => $notification->data['title'] ?? __('إشعار'),
                'body' => $notification->data['body'] ?? '',
                'url' => $notification->data['url'] ?? null,
                'read' => $notification->read_at !== null,
                'at' => $notification->created_at->diffForHumans(),
                'group' => self::groupOf($notification->data['type'] ?? ''),
            ]);

        // مركز تنبيهات لا صندوق بريد (بند ٨): التصفية بحسب ما يهم المستخدم الآن.
        $group = (string) $request->query('group', '');

        if ($group !== '' && $request->wantsJson() === false) {
            $notifications = $notifications->where('group', $group)->values();
        }

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

        return back()->with('status', __('عُلّمت كل الإشعارات كمقروءة.'));
    }

    /**
     * تجميع أنواع التنبيهات بلغة اهتمام المستخدم لا بأسماء الأصناف:
     * «تقاريري» و«المتابعة» و«الرصيد» و«المهام».
     */
    private static function groupOf(string $type): string
    {
        return match ($type) {
            'report_ready', 'analysis_failed' => 'reports',
            'live_report_changed', 'weekly_pulse' => 'watch',
            'low_credits', 'query_budget_warning' => 'billing',
            'task_overdue' => 'tasks',
            default => 'other',
        };
    }
}
