<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\AiReadiness\ReadinessCollector;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\FixList;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Policies\ProjectOwnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    ) {}

    /**
     * التشخيص الكامل: الدرجة والمحاور وقائمة الإصلاح.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json([
            'data' => [
                'project' => ['slug' => $project->slug, 'name' => $project->name],
                'maturity' => $this->maturity->compute($project),
                'readiness' => $this->scorer->score($project, Axis::AiReadiness)->toArray(),
                'fixes' => $this->fixes->build($project, [Axis::AiReadiness]),
                'website' => $project->profile?->website,
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

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);
    }
}
