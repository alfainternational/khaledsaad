<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Subscription;
use App\Models\ToolRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * غرفة العمليات (بنود ٢٠ و٢٢ و٣٠): صحة النظام، قمع التحويل، سجل التدقيق.
 *
 * كانت الصحة تُقرأ بسكربتات طرفية (prod-diag/prod-log) لا يراها إلا من
 * يملك SSH، وأعطال المفاتيح تُكتشف متأخرة. هذه الشاشة تعرضها لمن يملك
 * القرار — بلا أي استدعاء خارجي، من قاعدة البيانات والإعدادات فقط.
 */
class OperationsController extends Controller
{
    public function index(): View
    {
        return view('admin.operations', [
            'queue' => $this->queueHealth(),
            'failures' => $this->recentFailures(),
            'providers' => $this->providerKeys(),
            'funnel' => $this->funnel(),
            'audit' => AuditLog::with('actor:id,name')->latest('created_at')->limit(50)->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function queueHealth(): array
    {
        return [
            'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'last_failed_at' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->latest('failed_at')->value('failed_at')
                : null,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ToolRun> */
    private function recentFailures()
    {
        return ToolRun::where('status', ToolRun::STATUS_FAILED)
            ->latest('completed_at')
            ->limit(10)
            ->with(['toolVersion.tool:id,key,title', 'project:id,name'])
            ->get();
    }

    /**
     * حالة مفاتيح المزوّدين: مضبوط/غائب فقط — لا قيم ولا استدعاء اتصال
     * (الاستدعاء يكلّف، والغياب هو ما أسقط التوليد ثلاث مرات سابقًا).
     *
     * @return array<string, bool>
     */
    private function providerKeys(): array
    {
        return collect((array) config('services.ai.providers', []))
            ->map(fn ($provider) => trim((string) ($provider['key'] ?? '')) !== '')
            ->all();
    }

    /**
     * قمع التحويل (بند ٣٠) — آخر ٣٠ يومًا، وكل رقم بأساسه (§١٣).
     *
     * @return array<string, int>
     */
    private function funnel(): array
    {
        $since = now()->subDays(30);

        return [
            'guests' => Schema::hasTable('guest_sessions') ? GuestSession::where('created_at', '>=', $since)->count() : 0,
            'registered' => User::where('created_at', '>=', $since)->count(),
            'activated' => ToolRun::where('status', ToolRun::STATUS_COMPLETED)
                ->where('completed_at', '>=', $since)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'paying' => Subscription::where('status', 'active')
                ->whereHas('plan', fn ($query) => $query->where('price', '>', 0))
                ->count(),
        ];
    }
}
