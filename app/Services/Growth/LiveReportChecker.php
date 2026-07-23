<?php

namespace App\Services\Growth;

use App\Models\Project;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Services\Tools\DeterministicScorer;
use App\Models\User;

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

    public function __construct(private readonly DeterministicScorer $scorer) {}

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
                    'text' => 'تغيّر «'.self::PROFILE_LABELS[$field].'» منذ إصدار التقرير.',
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
                    ? 'أضفت شريحة جمهور جديدة لم تدخل في هذا التحليل.'
                    : 'حذفت شريحة جمهور كان التحليل مبنيًا عليها.',
            ];
        }

        // 4) الدرجة: نعيد حسابها بنفس القواعد الحتمية على الإجابات المحدّثة.
        $drift = $this->scoreDrift($watcher, $report);

        if ($drift !== null) {
            $changes[] = $drift;
        }

        return $changes;
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

        $current = $this->scorer->score($version, [...$frozen, ...$updated]);
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
