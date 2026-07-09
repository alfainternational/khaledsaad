<?php

namespace App\Domain\AI\Kernel\Agents;

use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\FeatureFlag\Services\FeatureFlagService;
use App\Support\Contexts\WorkspaceContext;

/**
 * كتالوج قدرات الوكلاء — القارئ المبوَّب لـ config/agent_registry.php وحكَم
 * «الكشف الانتقائي». يجسّد سلسلة التحقّق (الدستور §18) مضغوطةً في قرار واحد:
 * الحالة (lifecycle) ← الصلاحية (entitlement) ← مفتاح الميزة (feature flag).
 *
 * القاعدة الحاكمة: أي كود يسأل «هل تُعرض هذه القدرة؟» يسأل الكتالوج، لا اسم
 * الباقة ولا شرطاً hardcoded في الواجهة.
 */
class AgentCatalog
{
    /** @var array<string, AgentDefinition>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly EntitlementResolver $entitlements,
        private readonly FeatureFlagService $flags,
    ) {}

    /**
     * @return array<string, AgentDefinition>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $out = [];
        /** @var array<string, array<string, mixed>> $registry */
        $registry = (array) config('agent_registry', []);
        foreach ($registry as $code => $data) {
            if (is_string($code) && is_array($data)) {
                $out[$code] = AgentDefinition::fromArray($code, $data);
            }
        }

        return $this->cache = $out;
    }

    public function get(string $code): ?AgentDefinition
    {
        return $this->all()[$code] ?? null;
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function forStage(int $stage): array
    {
        return array_filter($this->all(), fn (AgentDefinition $d): bool => $d->stage === $stage);
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function forWave(int $wave): array
    {
        return array_filter($this->all(), fn (AgentDefinition $d): bool => $d->wave === $wave);
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function forCluster(string $cluster): array
    {
        return array_filter($this->all(), fn (AgentDefinition $d): bool => $d->cluster === $cluster);
    }

    /**
     * @return array<string, AgentDefinition>
     */
    public function forPersona(string $persona): array
    {
        return array_filter($this->all(), fn (AgentDefinition $d): bool => $d->servesPersona($persona));
    }

    /**
     * القدرات المكشوفة فعلاً لهذا السياق (بعد الصلاحية والميزة والحالة).
     *
     * @return array<string, AgentDefinition>
     */
    public function exposedFor(?WorkspaceContext $context, bool $isSuperAdmin = false): array
    {
        return array_filter(
            $this->all(),
            fn (AgentDefinition $d): bool => $this->isExposed($d, $context, $isSuperAdmin),
        );
    }

    public function isExposed(AgentDefinition $definition, ?WorkspaceContext $context, bool $isSuperAdmin = false): bool
    {
        $workspace = $context?->workspace;

        $entitled = $definition->isCore()
            || $this->truthy($this->entitlements->value($definition->entitlement, $workspace));

        $flagOn = $definition->featureFlag === null
            || $this->flags->isEnabled($definition->featureFlag, $context);

        return $this->decide($definition, $entitled, $flagOn, $isSuperAdmin);
    }

    /**
     * القرار النقي (قابل للاختبار بلا قاعدة بيانات): يترجم حالة القدرة + نتيجة
     * الصلاحية + نتيجة الميزة إلى «تُعرض/لا تُعرض».
     */
    public function decide(AgentDefinition $definition, bool $entitled, bool $flagOn, bool $isSuperAdmin): bool
    {
        // مبنية لكن غير مكشوفة (hidden) أو داخلية (internal): للآدمن الأعلى فقط للمعاينة.
        if ($definition->status === 'hidden' || $definition->status === 'internal') {
            return $isSuperAdmin;
        }

        // beta / ga: تخضع للصلاحية والميزة معاً.
        if ($definition->entitlement !== null && ! $entitled) {
            return false;
        }

        if ($definition->featureFlag !== null && ! $flagOn) {
            return false;
        }

        return true;
    }

    /** قيمة الصلاحية «مُفعّلة»: true، أو رقم موجب (حصص/حدود)، أو نص غير فارغ. */
    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === false || $value === '' || $value === 0 || $value === '0') {
            return false;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? ($value !== '');
        }

        return (bool) $value;
    }
}
