<?php

namespace App\Modules\Reporting;

use App\Models\Project;
use App\Modules\AiReadiness\SiteAuditResult;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\FixList;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بطاقة الجاهزية التقنية وتقرير الزحف — مخرج المرحلة ١ المباع.
 *
 * ما يميّزه عن تقرير الأدوات: كل بند فيه مرصود من الموقع وسجل الخادم، لا
 * مأخوذ من وصف صاحب النشاط لنفسه. لذلك لا يحمل وسم «فرضية» ولا يحتاجه.
 *
 * القرار الذي يمكّنه: «ما أصلحه هذا الأسبوع» — ولذلك كل بند ساقط يحمل سببه
 * وإصلاحه، وقائمة الإصلاح مرتّبة على الأثر × الجهد لا على ترتيب الفحص.
 */
class ReadinessCardPdfGenerator
{
    private const DISK = 'local';

    /** يرتفع مع كل تغيير في القالب كي تتجدد الملفات المخزّنة القديمة. */
    private const TEMPLATE_VERSION = 1;

    public function __construct(
        private readonly AxisScorer $scorer,
        private readonly FixList $fixes,
        private readonly ArabicPdfEngine $engine,
    ) {}

    /**
     * @param  array<string, mixed>|null  $crawl  ملخّص CrawlLogAnalyzer، أو null إن لم يُرفع سجل
     */
    public function generate(Project $project, SiteAuditResult $audit, ?array $crawl = null): string
    {
        $score = $this->scorer->score($project, Axis::AiReadiness);

        $html = view('reports.readiness-card', [
            'project' => $project,
            'audit' => $audit,
            'crawl' => $crawl,
            'score' => $score,
            'fixes' => $this->fixes->build($project, [Axis::AiReadiness], $audit),
            'brand' => config('brand'),
            'generatedAt' => now(),
        ])->render();

        $mpdf = $this->engine->make(__('بطاقة الجاهزية'));
        $mpdf->WriteHTML($html);

        $path = $this->path($project);
        Storage::disk(self::DISK)->put($path, $mpdf->Output('', 'S'));

        return $path;
    }

    /**
     * @param  array<string, mixed>|null  $crawl
     */
    public function download(Project $project, SiteAuditResult $audit, ?array $crawl = null): StreamedResponse
    {
        $path = $this->generate($project, $audit, $crawl);

        return Storage::disk(self::DISK)->download($path, "بطاقة-الجاهزية-{$project->slug}.pdf");
    }

    private function path(Project $project): string
    {
        return sprintf('readiness/readiness-%d-v%d.pdf', $project->id, self::TEMPLATE_VERSION);
    }
}
