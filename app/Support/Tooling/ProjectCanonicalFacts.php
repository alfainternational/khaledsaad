<?php

namespace App\Support\Tooling;

use App\Domain\Project\Models\Project;
use App\Domain\WorkspaceData\Models\WorkspaceData;

/**
 * محلِّل الحقائق القانونية للمشروع — مصدر الحقيقة الموحّد (الدستور §6/§30).
 *
 * أي حقل بنيوي (جمهور، عرض، تموضع...) يُشتقّ أولاً من مفاتيح workspace_data
 * المعيارية التي تكتبها الأدوات (ideal_customer, offer, positioning...)، ثم
 * يسقط على ملف المساحة (business.profile) كاحتياطي فقط. هذا يمنع أن يقرأ
 * الاستوديو أو الإدخال جمهوراً من أونبوردنج قديم يناقض ما أنتجته الأدوات.
 */
class ProjectCanonicalFacts
{
    /** @var array<string, string|null> */
    private array $cache = [];

    public function __construct(
        private readonly int $workspaceId,
        private readonly ?int $projectId,
    ) {}

    public static function for(Project $project): self
    {
        return new self($project->workspace_id, $project->id);
    }

    /**
     * القيمة المعيارية لمفتاح واحد (مثل ideal_customer/offer/positioning) إن وُجدت.
     * تقرأ الشكل الموحّد value_json['value'] الذي يكتبه RunToolAction.
     */
    public function value(string $key): ?string
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if ($this->projectId === null) {
            return $this->cache[$key] = null;
        }

        $row = WorkspaceData::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('project_id', $this->projectId)
            ->where('key', $key)
            ->first();

        $value = is_array($row?->value_json) ? ($row->value_json['value'] ?? null) : null;

        return $this->cache[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * الجمهور المحسوم: العميل المثالي (ideal_customer) الذي أنتجته الأدوات أولاً،
     * ثم جمهور ملف المساحة كاحتياطي فقط. يعيد القيمة ومصدرها لتوضيح المنشأ في
     * الواجهة والسجل ومنع تسرّب جمهور خاطئ إلى المخرجات.
     *
     * @param  array<string, mixed>  $profile
     * @return array{value: ?string, source: string}
     */
    public function audience(array $profile): array
    {
        $canonical = $this->value('ideal_customer');
        if ($canonical !== null) {
            return ['value' => $canonical, 'source' => 'ideal_customer'];
        }

        $profileAudience = trim((string) ($profile['audience'] ?? ''));
        if ($profileAudience !== '') {
            return ['value' => $profileAudience, 'source' => 'profile'];
        }

        return ['value' => null, 'source' => 'none'];
    }
}
