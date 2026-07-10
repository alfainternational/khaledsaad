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
                'gemini_key_hint' => $this->maskKey(config('services.gemini.key')),
                'nvidia_key_hint' => $this->maskKey(config('services.nvidia.key')),
                'gemini_model' => (string) config('services.gemini.model'),
                'nvidia_model' => (string) config('services.nvidia.model'),
                'nvidia_max_tokens' => (int) config('services.nvidia.max_tokens', 8192),
                'nvidia_timeout' => (int) config('services.nvidia.timeout', 45),
                'gemini_timeout' => (int) config('services.gemini.timeout', 45),
                'chain' => (string) config('services.ai.chain', 'groq,cerebras,nvidia'),
                'groq_key_hint' => $this->maskKey(config('services.ai.providers.groq.key')),
                'groq_model' => (string) config('services.ai.providers.groq.model'),
                'cerebras_key_hint' => $this->maskKey(config('services.ai.providers.cerebras.key')),
                'cerebras_model' => (string) config('services.ai.providers.cerebras.model'),
                'openrouter_key_hint' => $this->maskKey(config('services.ai.providers.openrouter.key')),
                'openrouter_model' => (string) config('services.ai.providers.openrouter.model'),
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
            'provider' => 'required|in:gemini,nvidia,fallback,chain,groq,cerebras,openrouter',
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

    /**
     * إدارة المزوّدات والمفاتيح والسرعة من الآدمن (تُخزَّن في SettingsStore فوق config).
     * المفاتيح أسرار: «اتركه فارغاً للإبقاء»، ولا تُسجَّل قيمتها في التدقيق أبداً.
     */
    public function updateProviders(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'chain' => 'required|string|max:120',
            'gemini_key' => 'nullable|string|max:400',
            'gemini_model' => 'required|string|max:120',
            'gemini_timeout' => 'required|integer|min:10|max:120',
            'nvidia_key' => 'nullable|string|max:400',
            'nvidia_model' => 'required|string|max:160',
            'nvidia_max_tokens' => 'required|integer|min:256|max:16384',
            'nvidia_timeout' => 'required|integer|min:10|max:120',
            'groq_key' => 'nullable|string|max:400',
            'groq_model' => 'required|string|max:160',
            'cerebras_key' => 'nullable|string|max:400',
            'cerebras_model' => 'required|string|max:160',
            'openrouter_key' => 'nullable|string|max:400',
            'openrouter_model' => 'required|string|max:200',
        ]);

        $set = [
            'services.ai.chain' => $this->sanitizeChain($validated['chain']),
            'services.gemini.model' => trim($validated['gemini_model']),
            'services.gemini.timeout' => (int) $validated['gemini_timeout'],
            'services.nvidia.model' => trim($validated['nvidia_model']),
            'services.nvidia.max_tokens' => (int) $validated['nvidia_max_tokens'],
            'services.nvidia.timeout' => (int) $validated['nvidia_timeout'],
            'services.ai.providers.groq.model' => trim($validated['groq_model']),
            'services.ai.providers.cerebras.model' => trim($validated['cerebras_model']),
            'services.ai.providers.openrouter.model' => trim($validated['openrouter_model']),
        ];

        // أسرار: تُكتب فقط عند إدخال قيمة جديدة (فارغ = إبقاء الحالي).
        $changed = [];
        foreach ([
            'gemini_key' => 'services.gemini.key',
            'nvidia_key' => 'services.nvidia.key',
            'groq_key' => 'services.ai.providers.groq.key',
            'cerebras_key' => 'services.ai.providers.cerebras.key',
            'openrouter_key' => 'services.ai.providers.openrouter.key',
        ] as $field => $settingKey) {
            $changed[$field] = filled($validated[$field] ?? null);
            if ($changed[$field]) {
                $set[$settingKey] = trim((string) $validated[$field]);
            }
        }

        $this->settings->setMany($set);

        // تدقيق بلا كشف الأسرار: نسجّل التغيّر لا القيمة.
        $this->auditLogger->record(
            action: 'admin.ai_control.providers_updated',
            targetType: 'ai_settings',
            actor: $request->user(),
            meta: [
                'chain' => $set['services.ai.chain'],
                'gemini_model' => $set['services.gemini.model'],
                'nvidia_model' => $set['services.nvidia.model'],
                'groq_model' => $set['services.ai.providers.groq.model'],
                'cerebras_model' => $set['services.ai.providers.cerebras.model'],
                'openrouter_model' => $set['services.ai.providers.openrouter.model'],
                'keys_changed' => array_keys(array_filter($changed)),
            ],
        );

        return back()->with('success', 'تم تحديث المزوّدات والمفاتيح والسرعة.');
    }

    /** تنقية سلسلة المزوّدات: أسماء معروفة فقط، مرتّبة، بلا تكرار. */
    private function sanitizeChain(string $chain): string
    {
        $allowed = ['groq', 'cerebras', 'openrouter', 'nvidia', 'gemini'];
        $names = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $chain)),
            fn (string $n): bool => in_array($n, $allowed, true),
        )));

        return implode(',', $names !== [] ? $names : ['groq', 'cerebras', 'nvidia']);
    }

    /** إخفاء المفتاح للعرض: لا يُكشف كاملاً في الواجهة أبداً. */
    private function maskKey(mixed $key): string
    {
        $key = (string) $key;
        if ($key === '') {
            return 'غير مضبوط';
        }

        return mb_strlen($key) <= 4 ? '••••' : '••••'.mb_substr($key, -4);
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
