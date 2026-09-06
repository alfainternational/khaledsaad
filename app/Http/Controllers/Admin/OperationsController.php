<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GuestSession;
use App\Models\Subscription;
use App\Models\ToolRun;
use App\Models\User;
use App\Support\AI\Resilience\CircuitBreaker;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\SpendGuard;
use Illuminate\Support\Collection;
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
    public function index(
        FallbackChainGateway $chain,
        CircuitBreaker $breaker,
        SpendGuard $spend,
    ): View {
        return view('admin.operations', [
            'queue' => $this->queueHealth(),
            // ما بُني للمراقبة يجب أن يُرى: تنبيهٌ في البريد وحده يُقرأ
            // متأخرًا، والقرار يُتخذ أمام لوحة لا في صندوق وارد.
            'provider_health' => $this->providerHealth($chain, $breaker),
            'spend' => $this->spend($spend),
            'deferred' => $this->deferred(),
            'margins' => $this->toolMargins(),
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

    /**
     * صحة كل مزوّد في السلسلة، وقدرتنا الكلية على الخدمة.
     *
     * كان الجدول يعرض «مفتاح مضبوط أم غائب» فقط — وهو سؤالٌ يُجاب مرة
     * عند الإعداد. السؤال الذي يتكرر كل يوم هو: **هل يعمل الآن؟** ونفادُ
     * الاشتراك لا يظهر في وجود المفتاح إطلاقًا، وهو ما أوقف المنصة.
     *
     * القراءة من القاطع لا باستدعاء المزوّد: فتحُ الصفحة لا يجوز أن
     * يكلّف مالًا، والقاطع يحمل آخر ما عرفناه فعلًا.
     *
     * @return array<int, array<string, mixed>>
     */
    private function providerHealth(FallbackChainGateway $chain, CircuitBreaker $breaker): array
    {
        $rows = [];

        foreach ($chain->health() as $provider => $health) {
            $rows[] = [
                'provider' => $provider,
                'state' => $health->value,
                'label' => $health->label(),
                'serving' => $health->canServe(),
                'failures' => $breaker->failures($provider),
            ];
        }

        return $rows;
    }

    /**
     * الإنفاق اليومي مقابل السقف.
     *
     * `null` في `ratio` تعني «بلا سقف» لا «صفر إنفاق» — والخلط بينهما
     * يجعل اللوحة تطمئن حيث يجب أن تُنذر.
     *
     * @return array<string, mixed>
     */
    private function spend(SpendGuard $guard): array
    {
        return [
            'today' => round($guard->spentToday(), 4),
            'cap' => $guard->cap(),
            'ratio' => $guard->ratio(),
            'has_capacity' => $guard->hasCapacity(),
        ];
    }

    /**
     * التشغيلات المؤجَّلة وأعمارها — أهم رقم تشغيلي في الصفحة.
     *
     * كل صفٍّ هنا مستخدمٌ بذل مجهوده وينتظرنا. وعمرُ أقدمها هو الجواب عن
     * «كم صبّرنا أحدهم؟»، وهو سؤالٌ لم يكن له جواب حين وقع العطل.
     *
     * @return array<string, mixed>
     */
    private function deferred(): array
    {
        $runs = ToolRun::where('status', ToolRun::STATUS_AWAITING_CAPACITY)
            ->with(['toolVersion.tool:id,key,title', 'project:id,name'])
            ->orderBy('updated_at')
            ->limit(15)
            ->get();

        $oldest = ToolRun::where('status', ToolRun::STATUS_AWAITING_CAPACITY)->min('updated_at');

        return [
            'count' => ToolRun::where('status', ToolRun::STATUS_AWAITING_CAPACITY)->count(),
            'oldest_at' => $oldest,
            'runs' => $runs,
        ];
    }

    /**
     * تكلفة كل أداة مقابل سعرها بالأرصدة — الهامش الحقيقي.
     *
     * بدونه لا نعرف أي أداة تُباع بخسارة، فنسعّر بالحدس. آخر ثلاثين يومًا
     * لأن أسعار المزوّدين تتغيّر، ومتوسطُ سنةٍ يخفي تغيّرًا وقع الشهر الماضي.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toolMargins(): array
    {
        if (! Schema::hasTable('ai_usage_records')) {
            return [];
        }

        return DB::table('ai_usage_records')
            ->join('tool_runs', 'tool_runs.id', '=', 'ai_usage_records.tool_run_id')
            ->join('tool_versions', 'tool_versions.id', '=', 'tool_runs.tool_version_id')
            ->join('tools', 'tools.id', '=', 'tool_versions.tool_id')
            ->where('ai_usage_records.created_at', '>=', now()->subDays(30))
            ->groupBy('tools.key', 'tools.title', 'tool_versions.credit_cost')
            ->selectRaw('tools.key, tools.title, tool_versions.credit_cost,
                COUNT(DISTINCT tool_runs.id) as runs,
                SUM(ai_usage_records.cost_usd) as cost_usd')
            ->orderByDesc('cost_usd')
            ->get()
            ->map(fn ($row) => [
                'key' => $row->key,
                'title' => $row->title,
                'credit_cost' => (int) $row->credit_cost,
                'runs' => (int) $row->runs,
                'cost_usd' => round((float) $row->cost_usd, 4),
                // التكلفة لكل تشغيل هي ما يُقارَن بالسعر، لا المجموع.
                'cost_per_run' => $row->runs > 0
                    ? round((float) $row->cost_usd / (int) $row->runs, 4)
                    : null,
            ])
            ->all();
    }

    /** @return Collection<int, ToolRun> */
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
