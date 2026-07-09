<?php

namespace App\Http\Controllers\Admin;

use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Models\AICreditsLedger;
use App\Domain\AI\Services\AiHealthChecker;
use App\Domain\AI\Services\AiMetrics;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Support\Settings\SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * مركز تحكم الذكاء — يدير ويراقب طبقة الذكاء المحلية الجديدة:
 * البوابات، الكاش، حارس الرصيد، البحث الحيّ، قاعدة المعرفة، والتعلّم.
 */
class AiControlController extends Controller
{
    /** المفاتيح القابلة للتبديل من الآدمن (allowlist). */
    private const BOOL_KEYS = [
        'services.ai.cache',
        'services.ai.enforce_credits',
        'services.web_search.enrich_tools',
    ];

    public function __construct(
        private readonly SettingsStore $settings,
        private readonly KnowledgeStore $knowledge,
        private readonly AuditLogger $auditLogger,
        private readonly AiHealthChecker $health,
        private readonly AiMetrics $metrics,
    ) {}

    public function index(): View
    {
        $knowledgeEntries = $this->knowledge->all();

        $patterns = null;
        $webKnowledge = [];
        foreach ($knowledgeEntries as $entry) {
            $key = (string) ($entry['key'] ?? '');
            if ($key === 'patterns.global') {
                $patterns = $entry['data'] ?? null;
            } elseif (str_starts_with($key, 'web.')) {
                $webKnowledge[] = $entry;
            }
        }

        $creditUsage = AICreditsLedger::query()
            ->where('delta', '<', 0)
            ->selectRaw('reason, COUNT(*) as hits, SUM(ABS(delta)) as spent')
            ->groupBy('reason')
            ->orderByDesc('spent')
            ->get();

        return view('admin.ai-control.index', [
            'status' => [
                'provider' => config('services.ai.provider'),
                'cache' => (bool) config('services.ai.cache', true),
                'enforce_credits' => (bool) config('services.ai.enforce_credits', false),
                'search_provider' => config('services.web_search.provider'),
                'enrich_tools' => (bool) config('services.web_search.enrich_tools', true),
                'gemini_ready' => (bool) config('services.gemini.key'),
                'nvidia_ready' => (bool) config('services.nvidia.key'),
                'verify_tls' => (bool) config('services.gemini.verify_tls', true),
                'kill_switch' => (bool) config('services.ai.kill_switch', false),
                'cascade' => (bool) config('services.ai.cascade', true),
                'cascade_threshold' => (int) config('services.ai.cascade_threshold', 60),
                'quality_judge' => (bool) config('services.ai.quality_judge', true),
            ],
            'health' => $this->health->check(),
            'metrics' => $this->metrics->snapshot(),
            'patterns' => is_array($patterns) ? $patterns : null,
            'webKnowledge' => $webKnowledge,
            'knowledgeCount' => count($knowledgeEntries),
            'creditUsage' => $creditUsage,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:gemini,nvidia,fallback',
            'search_provider' => 'required|in:duckduckgo',
            'cache' => 'required|boolean',
            'enforce_credits' => 'required|boolean',
            'enrich_tools' => 'required|boolean',
            'kill_switch' => 'required|boolean',
            'cascade' => 'required|boolean',
            'cascade_threshold' => 'required|integer|min:0|max:100',
            'quality_judge' => 'required|boolean',
        ]);

        $this->settings->setMany([
            'services.ai.provider' => $validated['provider'],
            'services.web_search.provider' => $validated['search_provider'],
            'services.ai.cache' => (bool) $validated['cache'],
            'services.ai.enforce_credits' => (bool) $validated['enforce_credits'],
            'services.web_search.enrich_tools' => (bool) $validated['enrich_tools'],
            'services.ai.kill_switch' => (bool) $validated['kill_switch'],
            'services.ai.cascade' => (bool) $validated['cascade'],
            'services.ai.cascade_threshold' => (int) $validated['cascade_threshold'],
            'services.ai.quality_judge' => (bool) $validated['quality_judge'],
        ]);

        $this->auditLogger->record(
            action: 'admin.ai_control.settings_updated',
            targetType: 'ai_settings',
            actor: $request->user(),
            meta: $validated,
        );

        return back()->with('success', 'تم تحديث إعدادات الذكاء.');
    }

    public function learn(Request $request): RedirectResponse
    {
        Artisan::call('ai:learn');

        $this->auditLogger->record(
            action: 'admin.ai_control.learn_triggered',
            targetType: 'ai_settings',
            actor: $request->user(),
            meta: ['output' => trim(Artisan::output())],
        );

        return back()->with('success', 'تم تشغيل التعلّم: '.trim(Artisan::output()));
    }

    public function forgetKnowledge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:200',
        ]);

        $this->knowledge->forget($validated['key']);

        $this->auditLogger->record(
            action: 'admin.ai_control.knowledge_forgotten',
            targetType: 'ai_knowledge',
            actor: $request->user(),
            meta: ['key' => $validated['key']],
        );

        return back()->with('success', 'تم حذف إدخال المعرفة: '.$validated['key']);
    }
}
