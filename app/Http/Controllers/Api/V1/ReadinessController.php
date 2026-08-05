<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\AiReadiness\ReadinessCollector;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\Brain\BrainReader;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\FixList;
use App\Modules\Diagnosis\IndustryBenchmark;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Intake\IntakeCollector;
use App\Modules\Measurement\ImpactAnalyzer;
use App\Modules\Reporting\ReadinessCardPdfGenerator;
use App\Modules\Shared\Sectors\Sector;
use App\Policies\ProjectOwnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * عقد الجاهزية والتشخيص للتطبيق.
 *
 * يستدعي الخدمات نفسها التي يستدعيها الويب ويعيد المفاتيح نفسها بأسماء §١٢
 * حرفيًّا. أي اشتقاق هنا يجعل التطبيق يعرض رقمًا يختلف عن الموقع بلا سبب
 * ظاهر، وهو ما تمنعه معايير القبول.
 */
class ReadinessController extends Controller
{
    /**
     * النِّسَب تبقى عشرية في JSON حتى حين تكون قيمتها صحيحة.
     *
     * بلا هذا يُخرِج json_encode القيمة 1.0 كـ1، فيعود الحقل نفسه عشريًّا
     * مرة وصحيحًا مرة. عميل Flutter يكتب `as double` ينهار عند الثانية —
     * وهو عطل يظهر عند موقع مثالي التدقيق لا عند موقع مكسور.
     */
    private const JSON = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly AxisScorer $scorer,
        private readonly MaturityAggregator $maturity,
        private readonly FixList $fixes,
        private readonly ReadinessCollector $collector,
        private readonly IntakeCollector $intake,
        private readonly ScoreHistory $history,
        private readonly IndustryBenchmark $benchmark,
        private readonly BrainReader $brain,
        private readonly ImpactAnalyzer $impact,
        private readonly SiteAudit $audit,
        private readonly ReadinessCardPdfGenerator $pdf,
    ) {}

    /**
     * التشخيص الكامل: الدرجة والمحاور وقائمة الإصلاح.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        // العقد نفسه للويب والتطبيق: الجمع يسبق العرض في الاثنين، وإلا رأى
        // مستخدم التطبيق محاور أقل من مستخدم الويب بنفس الإجابات (§١٥ بند ٨).
        $this->intake->collect($project);

        return response()->json([
            'data' => [
                'project' => ['slug' => $project->slug, 'name' => $project->name],
                'maturity' => $this->maturity->compute($project),
                'readiness' => $this->scorer->score($project, Axis::AiReadiness)->toArray(),
                'fixes' => $this->fixes->build($project, [Axis::AiReadiness]),
                'website' => $project->profile?->website,
                'history' => [
                    'points' => $this->history->points($project),
                    'plottable' => $this->history->isPlottable($project),
                ],
                'benchmark' => $this->benchmark->for($project),
                'conflicts' => $this->brain->openConflictsWithValues($project),

                // أثر الإصلاحات: حركةٌ مرصودة ونسبتها فرضية (SPEC-advanced-impact).
                'impact' => $this->impact->forProject($project),
            ],
        ], options: self::JSON);
    }

    /**
     * تشغيل التدقيق التقني.
     */
    public function audit(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $url = $project->profile?->website;

        if (blank($url)) {
            return response()->json([
                'message' => 'أضف رابط موقعك في ملف المشروع أولًا، فبدونه لا يوجد ما يُفحص.',
            ], 422);
        }

        $result = $this->collector->collectSiteAudit($project, $url);

        return response()->json([
            'data' => [
                'reachable' => $result->reachable,
                'checklist' => $result->checklist(),
                'notes' => $result->notes,
                'readiness' => $this->scorer->score($project->fresh(), Axis::AiReadiness)->toArray(),
            ],
        ], options: self::JSON);
    }

    /**
     * رفع سجل الوصول وتحليله.
     */
    public function uploadLog(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'log' => ['required', 'file', 'max:20480'],
        ], [], ['log' => 'ملف السجل']);

        $summary = $this->collector->collectCrawlLog(
            $project,
            (string) file_get_contents($request->file('log')->getRealPath()),
        );

        return response()->json(['data' => $summary], options: self::JSON);
    }

    /**
     * بطاقة الجاهزية PDF — نظير `app.readiness.download`.
     *
     * كانت على الويب وحده، فمستخدم التطبيق يرى الشاشة ولا يملك تنزيلها. محروسة
     * بـ`diagnosis.full` كنظيرتها: التنزيل مخرج مستوى ١ لا ٠.
     */
    public function download(Request $request, Project $project): StreamedResponse
    {
        $this->authorizeProject($request, $project);

        $url = $project->profile?->website;

        if (blank($url)) {
            abort(422, 'أضف رابط موقعك في ملف المشروع أولًا.');
        }

        return $this->pdf->download($project, $this->audit->audit($url, Sector::declaredOrGeneral($project->sector)));
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);
    }
}
