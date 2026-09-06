<?php

declare(strict_types=1);

namespace App\Modules\Intake;

use App\Models\Project;
use App\Models\ProjectAnswer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * صلاحية الحقائق: أيّها لا يزال صحيحًا، وأيّها يحتاج تأكيدًا.
 *
 * المشكلة التي تحلّها: قاعدة الحقائق تجعل الأداة العاشرة لا تسأل شيئًا —
 * وهذا مكسبها. لكنها بذلك **تُثبّت** ما قيل مرة وتستعمله إلى الأبد. ميزانيةٌ
 * من أربعة أشهر تدخل تشخيص اليوم بلا أن يعرف صاحبها أننا نستعملها.
 *
 * ولا يُحلّ هذا بإعادة السؤال دائمًا (فيعود عبء الستين سؤالًا)، ولا بعدم
 * السؤال أبدًا (فيتعفّن الدماغ). يُحلّ بأن **لكل نوع حقيقة عمرًا يناسبه**:
 * اسم النشاط لا يتقادم، والميزانية تتقادم في ثلاثة أشهر، وأرقام الأداء في
 * شهر واحد.
 */
final class FactValidity
{
    /**
     * أعمار افتراضية بالأيام، مفاتيحها أنماطٌ في اسم الحقل.
     *
     * الترتيب مقصود: أول نمط يطابق يفوز، فالأخصّ يسبق الأعمّ. ولذلك
     * `monthly_budget` يلتقطه نمط الميزانية لا نمط `monthly`.
     *
     * @var array<string, int|null>
     */
    private const LIFETIMES = [
        // لا تتقادم: تغيّرها يعني مشروعًا آخر.
        'name' => null,
        'business_model' => null,
        'sector' => null,
        'industry' => null,

        // أرقام أداء تتحرّك شهريًّا؛ استعمالُ رقمٍ عمره فصلٌ يقلب القراءة.
        'traffic' => 30,
        'conversion' => 30,
        'revenue' => 30,
        'leads' => 30,
        'followers' => 30,

        // مال وخطط: تُراجَع كل ربع.
        'budget' => 90,
        'spend' => 90,
        'price' => 90,
        'pricing' => 90,

        // وصفٌ استراتيجي يتغيّر ببطء.
        'audience' => 180,
        'competitor' => 180,
        'channel' => 180,
        'goal' => 180,
    ];

    /** ما لا ينطبق عليه نمط: نصف سنة — طويلٌ بما يكفي ألّا يُزعج. */
    private const DEFAULT_LIFETIME_DAYS = 180;

    public function lifetimeDays(string $fieldKey): ?int
    {
        foreach (self::LIFETIMES as $needle => $days) {
            if (str_contains($fieldKey, $needle)) {
                return $days;
            }
        }

        return self::DEFAULT_LIFETIME_DAYS;
    }

    /**
     * متى تنتهي صلاحية هذه الحقيقة إن أُكِّدت الآن.
     */
    public function expiresAt(string $fieldKey, ?Carbon $from = null): ?Carbon
    {
        $days = $this->lifetimeDays($fieldKey);

        return $days === null ? null : ($from ?? now())->copy()->addDays($days);
    }

    /**
     * الحقائق التي انتهت صلاحيتها — تُعرض للتأكيد بضغطة لا لإعادة الكتابة.
     *
     * التأكيد أقلّ احتكاكًا من الإدخال بمراحل: من يرى رقمه مكتوبًا ويُسأل
     * «أهو كما هو؟» يجيب، ومن يُطلب منه كتابته من جديد يؤجّل.
     *
     * @return Collection<int, ProjectAnswer>
     */
    public function stale(Project $project): Collection
    {
        return ProjectAnswer::query()
            ->where('project_id', $project->id)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now())
            ->orderBy('valid_until')
            ->get();
    }

    public function isStale(ProjectAnswer $answer): bool
    {
        return $answer->valid_until !== null && $answer->valid_until->isPast();
    }

    /**
     * يجدّد صلاحية حقيقة أكّدها صاحبها دون تغييرها.
     */
    public function confirm(ProjectAnswer $answer): ProjectAnswer
    {
        $answer->forceFill([
            'confirmed_at' => now(),
            'valid_until' => $this->expiresAt($answer->field_key),
        ])->save();

        return $answer;
    }
}
