<?php

namespace App\Modules\Alerts;

use App\Models\Project;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Models\User;
use App\Modules\Diagnosis\DeterministicScorer;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Tools\ProjectContextResolver;

/**
 * فاحص التقرير الحي: يقارن ما بُني عليه التقرير (اللقطة المجمدة BR-005)
 * بحالة المشروع اليوم، ويجيب على سؤال واحد: هل ما زال هذا التقرير صادقًا؟
 *
 * الفحص حتمي بالكامل — لا استدعاء نموذج ولا تكلفة — لذا يمكن تشغيله يوميًا
 * على كل المراقبين دون قلق.
 */
class LiveReportChecker
{
    /**
     * حقول الملف التي تدخل في البصمة، بنفس ترتيب لقطة التقرير.
     */
    private const PROFILE_FIELDS = [
        'business_model', 'description', 'geography', 'website',
        'monthly_budget', 'primary_goal', 'value_proposition', 'channels',
    ];

    private const PROFILE_LABELS = [
        'business_model' => 'نموذج العمل',
        'description' => 'وصف المشروع',
        'geography' => 'النطاق الجغرافي',
        'website' => 'الموقع الإلكتروني',
        'monthly_budget' => 'الميزانية الشهرية',
        'primary_goal' => 'الهدف الأساسي',
        'value_proposition' => 'القيمة المميزة',
        'channels' => 'القنوات',
    ];

    public function __construct(
        private readonly DeterministicScorer $scorer,
        private readonly ProjectContextResolver $context,
        private readonly ScoreHistory $history,
    ) {}

    public function activate(Report $report, User $user): ReportWatcher
    {
        return ReportWatcher::updateOrCreate(
            ['report_id' => $report->id],
            [
                'project_id' => $report->project_id,
                'user_id' => $user->id,
                'status' => ReportWatcher::STATUS_ACTIVE,
                'baseline_fingerprint' => $this->fingerprint($report->project),
                'last_checked_at' => now(),
            ],
        );
    }

    /**
     * بصمة حالة المشروع الآن: أي تعديل في الملف أو الجمهور أو المنافسين
     * أو الإجابات المحفوظة يغيّرها.
     */
    public function fingerprint(Project $project): string
    {
        return hash('sha256', json_encode($this->currentState($project), JSON_UNESCAPED_UNICODE));
    }

    /**
     * الفحص الفعلي: يعيد قائمة تغييرات بلغة المستخدم، فارغة إن لم يتغير شيء.
     *
     * @return array<int, array{type: string, text: string}>
     */
    public function check(ReportWatcher $watcher): array
    {
        $report = $watcher->report;
        $project = $watcher->project;

        if ($report === null || $project === null) {
            return [];
        }

        $snapshot = $report->toolRun?->snapshot ?? [];
        $changes = [];

        // 1) حقول الملف: مقارنة قيمة بقيمة ضد لقطة التقرير المجمدة.
        $frozenProfile = (array) ($snapshot['profile'] ?? []);
        $currentProfile = $project->profile?->only(self::PROFILE_FIELDS) ?? [];

        foreach (self::PROFILE_FIELDS as $field) {
            if ($this->normalize($frozenProfile[$field] ?? null) !== $this->normalize($currentProfile[$field] ?? null)) {
                $changes[] = [
                    'type' => 'profile',
                    'text' => __('تغيّر «:field» منذ إصدار التقرير.', ['field' => self::PROFILE_LABELS[$field]]),
                ];
            }
        }

        // 2) المنافسون المؤكدون: الداخل الجديد أهم إشارة سوقية.
        $frozenCompetitors = collect($snapshot['competitors'] ?? [])->pluck('name')->filter()->all();
        $currentCompetitors = $project->competitors()
            ->where('status', 'confirmed')
            ->pluck('name')
            ->all();

        foreach (array_diff($currentCompetitors, $frozenCompetitors) as $name) {
            $changes[] = ['type' => 'competitor', 'text' => "أُضيف منافس جديد لم يعرفه التقرير: «{$name}»."];
        }

        // 3) الجمهور: إضافة شريحة أو حذفها يغيّر خريطة الاستهداف.
        $frozenAudiences = count($snapshot['audiences'] ?? []);
        $currentAudiences = $project->audiences()->count();

        if ($currentAudiences !== $frozenAudiences) {
            $changes[] = [
                'type' => 'audience',
                'text' => $currentAudiences > $frozenAudiences
                    ? __('أضفت شريحة جمهور جديدة لم تدخل في هذا التحليل.')
                    : __('حذفت شريحة جمهور كان التحليل مبنيًا عليها.'),
            ];
        }

        // 4) الدرجة: نعيد حسابها بنفس القواعد الحتمية على الإجابات المحدّثة.
        $drift = $this->scoreDrift($watcher, $report);

        if ($drift !== null) {
            $changes[] = $drift;
        }

        // 5) درجة النضج: المؤشر الذي يُشترى الاشتراك من أجله.
        $maturity = $this->maturityDrift($project);

        if ($maturity !== null) {
            $changes[] = $maturity;
        }

        return $changes;
    }

    /**
     * تغيّر `maturity_score` بين آخر قياسين مقيَّدين.
     *
     * لا يُعاد الحساب هنا: المقارنة بين ما قُيِّد وقته وما قُيِّد قبله. إعادة
     * حساب الماضي بقواعد الحاضر تجعل كل تغيير في المحرّك يظهر لصاحب النشاط
     * تغيّرًا في نشاطه.
     *
     * @return array{type: string, text: string}|null
     */
    private function maturityDrift(Project $project): ?array
    {
        $delta = $this->history->latestDelta($project);

        if ($delta === null) {
            return null;
        }

        /*
         * محور جديد دخل الحساب: الرقم تحرّك لأن ما نقيسه اتّسع، لا لأن النشاط
         * تغيّر. تنبيهٌ هنا يكذب على صاحبه بأصدق البيانات (§١٥).
         */
        if ($delta['coverage_changed']) {
            return null;
        }

        if (abs($delta['delta']) < (int) config('growth.score_drift_threshold', 5)) {
            return null;
        }

        return [
            'type' => MetricKey::MATURITY_SCORE,
            'text' => $delta['delta'] > 0
                ? "درجة نضجك التسويقي ارتفعت من {$delta['from']} إلى {$delta['to']} على نفس المحاور المقيسة."
                : "درجة نضجك التسويقي نزلت من {$delta['from']} إلى {$delta['to']} على نفس المحاور المقيسة.",
        ];
    }

    /**
     * إعادة حساب الدرجة على الإجابات الحالية (المجمدة + ما حُدّث في ذاكرة
     * المشروع بعدها). فرق يتجاوز العتبة يستحق تنبيهًا.
     *
     * @return array{type: string, text: string}|null
     */
    private function scoreDrift(ReportWatcher $watcher, Report $report): ?array
    {
        $run = $report->toolRun;
        $version = $run?->toolVersion;

        if ($version === null || $report->score === null) {
            return null;
        }

        $frozen = $run->answers->pluck('value_json', 'field_key')->all();
        $updated = $watcher->project->answers()->pluck('value_json', 'field_key')->all();
        $merged = [...$frozen, ...$updated];

        // نفس عدالة خط الأنابيب: القواعد المنطبقة على سياق هذا المشروع فقط.
        $contextual = [...$merged, ...$this->context->for($watcher->project)];
        $activeKeys = $version->fields
            ->filter(fn ($field) => $field->isVisible($contextual))
            ->pluck('key')
            ->all();

        $current = $this->scorer->score($version, $merged, $activeKeys);
        $delta = $current['score'] - (int) $report->score;

        if (abs($delta) < (int) config('growth.score_drift_threshold', 5)) {
            return null;
        }

        return [
            'type' => 'score',
            'text' => $delta > 0
                ? "درجتك اليوم تُحسب {$current['score']} بدل {$report->score} — تقدمك الفعلي أكبر مما يعرضه التقرير."
                : "درجتك اليوم تُحسب {$current['score']} بدل {$report->score} — التقرير يعرض وضعًا أفضل من الحالي.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentState(Project $project): array
    {
        $project->loadMissing(['profile', 'audiences', 'competitors']);

        return [
            'profile' => collect($project->profile?->only(self::PROFILE_FIELDS) ?? [])
                ->map(fn ($value) => $this->normalize($value))
                ->all(),
            'audiences' => $project->audiences->pluck('name')->sort()->values()->all(),
            'competitors' => $project->competitors
                ->where('status', 'confirmed')
                ->pluck('name')->sort()->values()->all(),
            'answers' => $project->answers()
                ->orderBy('field_key')
                ->pluck('value_json', 'field_key')
                ->all(),
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : $item, $value)));
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }
}
