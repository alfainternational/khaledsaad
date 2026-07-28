<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\AiReadiness\CrawlLogAnalyzer;
use App\Modules\AiReadiness\ReadinessCollector;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\FixList;
use App\Modules\Reporting\ReadinessCardPdfGenerator;
use App\Policies\ProjectOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * بطاقة الجاهزية: التدقيق التقني وتقرير الزحف.
 *
 * المتحكّم لا يحسب شيئًا (§١٤): يستدعي الجامع ثم الحاسبة ثم العارض. أي معادلة
 * هنا تصير غير قابلة للاختبار وتتفرّع إلى نسخ في كل شاشة.
 */
class ReadinessController extends Controller
{
    public function __construct(
        private readonly ReadinessCollector $collector,
        private readonly SiteAudit $audit,
        private readonly CrawlLogAnalyzer $crawl,
        private readonly AxisScorer $scorer,
        private readonly FixList $fixes,
        private readonly ReadinessCardPdfGenerator $pdf,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $url = $project->profile?->website;
        $score = $this->scorer->score($project, Axis::AiReadiness);

        return view('app.readiness.show', [
            'project' => $project,
            'website' => $url,
            'score' => $score,
            'fixes' => $this->fixes->build($project, [Axis::AiReadiness]),
            'crawl' => session('readiness.crawl'),
        ]);
    }

    /**
     * تشغيل التدقيق التقني.
     *
     * يعمل متزامنًا لا في الطابور: صاحب النشاط يضغط الزر وينتظر النتيجة،
     * وجلب صفحتين بمهلة ١٢ ثانية لا يستحق تعقيد الاستطلاع. المهمة في الطابور
     * موجودة للتشغيل الدوري لا للطلب اليدوي.
     */
    public function audit(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $url = $project->profile?->website;

        if (blank($url)) {
            throw ValidationException::withMessages([
                'website' => 'أضف رابط موقعك في ملف المشروع أولًا، فبدونه لا يوجد ما يُفحص.',
            ]);
        }

        $result = $this->collector->collectSiteAudit($project, $url);

        return redirect()
            ->route('app.readiness.show', $project)
            ->with('status', $result->reachable
                ? 'اكتمل الفحص.'
                : 'تعذّر الوصول إلى الموقع، فلم يُفحص شيء. تأكّد من الرابط ثم أعد المحاولة.');
    }

    /**
     * رفع سجل الوصول.
     *
     * الرفع مدخل أول مقصود: الموصّلات المباشرة تحتاج صلاحيات على الاستضافة
     * وتأتي لاحقًا، والرفع يعمل اليوم بلا انتظار أحد.
     */
    public function uploadLog(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            // السجلات نصّية؛ الحد الأعلى يمنع ملفًا ضخمًا من إسقاط الطلب.
            'log' => ['required', 'file', 'max:20480'],
        ], [], ['log' => 'ملف السجل']);

        $contents = (string) file_get_contents($request->file('log')->getRealPath());
        $summary = $this->collector->collectCrawlLog($project, $contents);

        $message = $summary['parsed_lines'] === 0
            ? 'تعذّرت قراءة السجل: لم يُفهم أي سطر. تأكّد أنه سجل وصول بصيغة Combined.'
            : "قُرئ {$summary['parsed_lines']} سطرًا، ورُصدت {$summary['total_visits']} زيارة بوت.";

        return redirect()
            ->route('app.readiness.show', $project)
            ->with('status', $message)
            // الملخّص في الجلسة لا في القاعدة: التقرير مشتق، والحقائق وحدها تُحفظ.
            ->with('readiness.crawl', $summary);
    }

    public function download(Request $request, Project $project): StreamedResponse
    {
        $this->authorizeProject($request, $project);

        $url = $project->profile?->website;

        if (blank($url)) {
            throw ValidationException::withMessages([
                'website' => 'أضف رابط موقعك أولًا.',
            ]);
        }

        return $this->pdf->download(
            $project,
            $this->audit->audit($url),
            session('readiness.crawl'),
        );
    }

    /**
     * عزل مساحات العمل عبر البوابة الموحّدة.
     *
     * `ProjectOwnership` هو المكان الوحيد الذي ينتهي إليه التحقق في المنصة.
     * كتابة شرط ملكية خاص هنا تخلق مسار وصول ثانيًا يمكن أن ينسى التحقق —
     * وهو بالضبط ما تمنعه تلك السياسة.
     */
    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);
    }
}
