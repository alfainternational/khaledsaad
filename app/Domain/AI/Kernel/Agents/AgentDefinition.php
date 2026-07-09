<?php

namespace App\Domain\AI\Kernel\Agents;

/**
 * تعريف قدرة وكيل واحد — قراءة نوعية لمدخل من config/agent_registry.php.
 *
 * كائن غير قابل للتغيير (immutable) يترجم صفّاً من السجلّ إلى بيانات قوية النوع
 * تستهلكها بقية النواة (الكتالوج، المهارات، الآدمن) بلا تخمين مفاتيح.
 */
final class AgentDefinition
{
    /**
     * @param  array<int, string>  $personas
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $cluster,
        public readonly int $stage,
        public readonly ?string $entitlement,
        public readonly ?string $featureFlag,
        public readonly array $personas,
        public readonly int $wave,
        public readonly string $status,
        public readonly string $surface,
        public readonly string $summary,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $code, array $data): self
    {
        return new self(
            code: $code,
            name: (string) ($data['name'] ?? $code),
            cluster: (string) ($data['cluster'] ?? 'creation'),
            stage: (int) ($data['stage'] ?? 0),
            entitlement: isset($data['entitlement']) ? (string) $data['entitlement'] : null,
            featureFlag: isset($data['feature_flag']) ? (string) $data['feature_flag'] : null,
            personas: array_values(array_map('strval', (array) ($data['personas'] ?? []))),
            wave: (int) ($data['wave'] ?? 0),
            status: (string) ($data['status'] ?? 'hidden'),
            surface: (string) ($data['surface'] ?? ''),
            summary: (string) ($data['summary'] ?? ''),
        );
    }

    /** قدرة بنية تحتية بلا واجهة مستخدم (لا تخصّ شريحة). */
    public function isInfrastructure(): bool
    {
        return $this->personas === [];
    }

    /** قدرة أساسية متاحة دائماً بلا صلاحية باقة. */
    public function isCore(): bool
    {
        return $this->entitlement === null;
    }

    public function servesPersona(string $persona): bool
    {
        return in_array($persona, $this->personas, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'cluster' => $this->cluster,
            'stage' => $this->stage,
            'entitlement' => $this->entitlement,
            'feature_flag' => $this->featureFlag,
            'personas' => $this->personas,
            'wave' => $this->wave,
            'status' => $this->status,
            'surface' => $this->surface,
            'summary' => $this->summary,
        ];
    }
}
