<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Modules\Diagnosis\DeterministicScorer;
use App\Modules\Diagnosis\ScoreExplainer;
use App\Services\Tools\AnswerCompleteness;
use App\Services\Tools\ProjectContextResolver;
use Illuminate\Console\Command;
use Throwable;

/**
 * يضيف شرح الدرجة إلى التقارير التي صدرت قبل بناء الشرح.
 *
 * التقرير يخزّن تفصيله لحظة إصداره، فالتقارير القديمة تحمل «10 / 10» بلا
 * سؤال ولا إجابة ولا حصة. هذا الأمر يعيد حساب التفصيل من إجابات التشغيل
 * نفسها ويضيف الشرح فقط.
 *
 * حارس لا يُتجاوز: إن اختلفت الدرجة المعاد حسابها عن المحفوظة، يُترك التقرير
 * كما هو ويُبلَّغ عنه. تغيير درجة صدرت للعميل يكسر المقارنة الزمنية ويخالف
 * كون التقرير سجلًّا لما قيل له وقتها — والشرح لا يستحق هذا الثمن.
 */
class BackfillScoreExplanations extends Command
{
    protected $signature = 'reports:backfill-score-explanation {--dry-run : اعرض ما سيتغير بلا كتابة}';

    protected $description = 'يضيف السؤال والإجابة والحصة وسلّم التقدير إلى تفصيل درجة التقارير القديمة';

    public function handle(
        DeterministicScorer $scorer,
        ScoreExplainer $explainer,
        AnswerCompleteness $completeness,
        ProjectContextResolver $context,
    ): int {
        $dry = (bool) $this->option('dry-run');
        $updated = $skipped = $drifted = $failed = 0;

        Report::with('toolRun.toolVersion.fields', 'toolRun.answers', 'toolRun.project')
            ->chunkById(100, function ($reports) use (
                $scorer, $explainer, $completeness, $context, $dry,
                &$updated, &$skipped, &$drifted, &$failed
            ) {
                foreach ($reports as $report) {
                    $section = $report->sections()->where('key', 'score')->first();
                    $run = $report->toolRun;

                    if ($section === null || $run === null || $run->toolVersion === null) {
                        $skipped++;

                        continue;
                    }

                    $content = $section->content_json ?? [];

                    // ما يحمل الحصة أصلًا مشروح بالفعل.
                    if (isset($content['breakdown'][0]['share'])) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $answers = $completeness->plainAnswers($run);
                        $activeKeys = $completeness
                            ->visibleFields($run->toolVersion, array_merge($answers, $context->for($run->project)))
                            ->pluck('key')
                            ->all();

                        $fresh = $explainer->explain(
                            $run->toolVersion,
                            $scorer->score($run->toolVersion, $answers, $activeKeys),
                        );
                    } catch (Throwable $e) {
                        $this->warn("تقرير {$report->id}: تعذّر إعادة الحساب — {$e->getMessage()}");
                        $failed++;

                        continue;
                    }

                    if ((int) $fresh['score'] !== (int) $report->score) {
                        $this->warn("تقرير {$report->id}: الدرجة المعاد حسابها {$fresh['score']} تخالف المحفوظة {$report->score} — تُرك كما هو.");
                        $drifted++;

                        continue;
                    }

                    $updated++;

                    if ($dry) {
                        continue;
                    }

                    $section->forceFill([
                        'content_json' => array_merge($content, [
                            'breakdown' => $fresh['breakdown'],
                            'excluded' => $fresh['excluded'] ?? [],
                            'total_weight' => $fresh['total_weight'] ?? 0,
                            'weights_basis' => 'الأوزان أدناه ترتيب أهمية وضعناه نحن بحكم منهجي، لا معايرة على بيانات حملات. هي تعكس أي بند نراه أخطر على نتيجتك، وتظل قابلة للمراجعة.',
                            'weights_scale' => ScoreExplainer::SCALE_NOTE,
                        ]),
                    ])->save();
                }
            });

        $this->info(($dry ? '[تجربة] ' : '')."شُرحت: {$updated} · متجاوَزة: {$skipped} · درجتها تغيّرت فتُركت: {$drifted} · أخفقت: {$failed}");

        return $drifted > 0 || $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
