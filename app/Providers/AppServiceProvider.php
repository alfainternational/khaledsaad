<?php

namespace App\Providers;

use App\Application\Integration\CloudIntegrationService;
use App\Contracts\AiGatewayInterface;
use App\Contracts\CloudClientContract;
use App\Contracts\WebSearchGateway;
use App\Domain\AI\Kernel\Agents\AgentCatalog;
use App\Domain\AI\Kernel\SkillRegistry;
use App\Domain\AI\Kernel\Skills\InsightSkill;
use App\Domain\AI\Kernel\Skills\NextStepSkill;
use App\Domain\AI\Kernel\Skills\ToolAnalysisSkill;
use App\Domain\AI\Kernel\Skills\WebResearchSkill;
use App\Domain\AI\Knowledge\VectorMath;
use App\Domain\AI\Semantic\LexicalSemanticMatcher;
use App\Domain\AI\Semantic\SemanticMatcher;
use App\Domain\AI\Services\AiGatewayFactory;
use App\Domain\AI\Services\AiMetrics;
use App\Domain\AI\Services\CachingAiGateway;
use App\Domain\AI\Services\ChainAiGateway;
use App\Domain\AI\Services\FallbackAiGateway;
use App\Domain\AI\Services\GeminiGateway;
use App\Domain\AI\Services\NullAiGateway;
use App\Domain\AI\Services\NvidiaNimGateway;
use App\Domain\AI\Services\PrivateWorkerAiGateway;
use App\Domain\AI\Web\DuckDuckGoSearchGateway;
use App\Domain\AI\Web\NullWebSearchGateway;
use App\Domain\Approval\Models\Approval;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Client\Models\Client;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Domain\Integration\Services\CloudIntegrationGate;
use App\Domain\Integration\Services\HttpCloudClient;
use App\Domain\Integration\Services\NullCloudClient;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Http\View\Composers\AmbientAdvisorComposer;
use App\Policies\ApprovalPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\WorkspaceInvitationPolicy;
use App\Policies\WorkspaceMemberPolicy;
use App\Policies\WorkspacePolicy;
use App\Support\Settings\SettingsStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsStore::class);
        $this->app->singleton(EntitlementResolver::class);
        $this->app->singleton(FeatureFlagService::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(VectorMath::class, fn () => new VectorMath(
            max(2, (int) config('services.knowledge.embedding_min_dimensions', 2)),
            max(2, (int) config('services.knowledge.embedding_max_dimensions', 4096)),
        ));
        $this->app->singleton(AiGatewayInterface::class, function ($app) {
            // Kill Switch: إيقاف فوري لكل نداءات LLM من لوحة الآدمن.
            if ((bool) config('services.ai.kill_switch', false)) {
                return new NullAiGateway;
            }

            $provider = config('services.ai.provider', 'gemini');
            $factory = new AiGatewayFactory;

            $gateway = match ($provider) {
                // سلسلة مزوّدات مرتّبة (Groq→Cerebras→NVIDIA…) — الصمود والجودة.
                'chain' => $factory->chain(array_filter(array_map(
                    'trim',
                    explode(',', (string) config('services.ai.chain', 'groq,cerebras,nvidia')),
                ))),
                'nvidia' => new NvidiaNimGateway,
                'groq', 'cerebras', 'openrouter' => $factory->make($provider) ?? new GeminiGateway,
                'fallback' => new FallbackAiGateway(
                    new GeminiGateway,
                    new NvidiaNimGateway,
                ),
                default => new GeminiGateway,
            };

            if (
                $provider !== 'private_worker'
                && (bool) config('services.private_worker.enabled', false)
                && (bool) config('services.private_worker.prefer_for_generation', true)
            ) {
                $gateway = new ChainAiGateway(
                    $app->make(PrivateWorkerAiGateway::class),
                    $gateway,
                );
            }

            // Phase هـ: cache identical prompts to cut paid AI spend.
            if ((bool) config('services.ai.cache', true)) {
                $gateway = new CachingAiGateway(
                    $gateway,
                    (int) config('services.ai.cache_ttl_minutes', 1440),
                    $app->make(AiMetrics::class),
                );
            }

            return $gateway;
        });

        // طبقة الفهم الدلالي المحلية: تُربط عبر عقد قابل للترقية لاحقاً لمحرّك تضمينات.
        $this->app->singleton(
            SemanticMatcher::class,
            LexicalSemanticMatcher::class,
        );

        $this->app->singleton(HttpCloudClient::class);

        $this->app->singleton(CloudClientContract::class, function ($app) {
            $http = $app->make(HttpCloudClient::class);

            return $http->configured() ? $http : new NullCloudClient;
        });

        $this->app->singleton(CloudIntegrationGate::class);
        $this->app->singleton(CloudIntegrationService::class);

        // نواة الوكيل المحلي (Agent Kernel): سجلّ المهارات يُبنى مرة واحدة لكل طلب.
        // مزوّد البحث الحيّ (مجرّد): الافتراضي DuckDuckGo بلا مفتاح؛ يمكن استبداله
        // بمزوّد بمفتاح من الإعداد لاحقاً دون لمس بقية النظام.
        $this->app->bind(WebSearchGateway::class, function ($app) {
            if ((bool) config('services.ai.kill_switch', false)) {
                return new NullWebSearchGateway;
            }

            return match (config('services.web_search.provider', 'duckduckgo')) {
                default => $app->make(DuckDuckGoSearchGateway::class),
            };
        });

        // كتالوج قدرات الوكلاء الـ25: المصدر الوحيد لـ«الكشف الانتقائي». مفرد
        // لأنه يقرأ config مرة ويخزّن التعريفات المبنية طوال الطلب.
        $this->app->singleton(AgentCatalog::class);

        $this->app->singleton(SkillRegistry::class, function ($app): SkillRegistry {
            $registry = new SkillRegistry($app);
            // الترتيب مهم: المهارات المحدّدة أولاً، ثم الاحتياطية العامة أخيراً.
            $registry->register(ToolAnalysisSkill::class);
            $registry->register(WebResearchSkill::class);
            $registry->register(InsightSkill::class);
            $registry->register(NextStepSkill::class);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // إعدادات الذكاء من الآدمن تُطبَّق فوق config() (الدستور §32): تلتقطها كل
        // المستهلكات (البوابة، الكاش، الرصيد، البحث) دون إعادة ربط. ملفّية بلا migration.
        try {
            foreach ($this->app->make(SettingsStore::class)->all() as $key => $value) {
                if (is_string($key) && (
                    str_starts_with($key, 'services.ai.')
                    || str_starts_with($key, 'services.web_search.')
                    || str_starts_with($key, 'services.nvidia.')
                    || str_starts_with($key, 'services.gemini.')
                    || str_starts_with($key, 'services.private_worker.')
                    || str_starts_with($key, 'services.knowledge.')
                )) {
                    config([$key => $value]);
                }
            }
        } catch (\Throwable) {
            // لا تُسقط الإقلاع إن تعذّر قراءة الإعدادات؛ تُستخدم قيم config الافتراضية.
        }

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Approval::class, ApprovalPolicy::class);
        Gate::policy(WorkspaceInvitation::class, WorkspaceInvitationPolicy::class);
        Gate::policy(WorkspaceMember::class, WorkspaceMemberPolicy::class);

        // AmbientAdvisor: العقل المحلي حاضر في كل صفحة من القالب الرئيسي.
        View::composer('layouts.app', AmbientAdvisorComposer::class);

        Blade::if('feature', fn (string $key): bool => feature($key));
        Blade::directive('entitlement', fn (string $expression): string => "<?php echo e(entitlement($expression)); ?>");

        // AI cost guard (data-sovereignty plan, Phase 0): in-house rate limit on AI assist
        // endpoints. Subscribers with the AI Studio module get a higher ceiling; everyone else
        // is throttled to protect paid AI spend until the self-hosted model lands.
        RateLimiter::for('ai-assist', function (Request $request): Limit {
            $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
            $canStudio = $workspace instanceof Workspace
                && app(EntitlementResolver::class)->boolean('modules.ai_studio', $workspace);
            $perMinute = $canStudio ? 60 : 15;
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute($perMinute)->by('ai-assist:'.$key);
        });
    }
}
