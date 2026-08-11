<?php

namespace App\Modules\Reporting;

use App\Models\Report;
use App\Services\Growth\NextToolSuggester;
use App\Support\Presentation\ReportPresenter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * يولّد PDF عربيًا للتقرير ويخزّنه، فلا يُعاد بناؤه عند كل تنزيل.
 *
 * المحرك mPDF لأنه الوحيد الذي يطبّق تشكيل الحروف العربية واتجاه RTL
 * فعليًا (dompdf يرسم المحارف منفصلة ومعكوسة).
 *
 * الملف المطبوع يحمل كل ما يراه المستخدم على الشاشة — لا نسخة مختصرة:
 * المقارنة الزمنية، حالة المراجعة، الرسوم، المنافسون، تفصيل الدرجة،
 * ما رصده التقرير الحي، والخطوة المقترحة تاليًا.
 */
class ReportPdfGenerator
{
    private const DISK = 'local';

    /**
     * يرتفع مع كل تغيير في قالب الـPDF كي تتجدد الملفات المخزّنة القديمة.
     */
    private const TEMPLATE_VERSION = 5;

    public function __construct(
        private readonly ReportPresenter $presenter,
        private readonly ReportCharts $charts,
        private readonly NextToolSuggester $suggester,
        private readonly ArabicPdfEngine $engine,
    ) {}

    /**
     * يعيد مسار الملف المخزّن، مولِّدًا إياه إن لزم.
     */
    public function ensure(Report $report): string
    {
        $path = $this->path($report);

        if ($report->pdf_path === $path && Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        return $this->generate($report);
    }

    public function generate(Report $report): string
    {
        $html = view('reports.pdf', [
            'report' => $this->presenter->full($report),
            'charts' => $this->charts->build($report),
            // نفس ما تعرضه صفحة التقرير في الويب والتطبيق.
            'comparison' => $this->presenter->comparison($report, $this->presenter->previousFor($report)),
            'watcher' => $report->watcher,
            'suggestion' => $this->suggester->suggest($report->project),
            'brand' => config('brand'),
            'generatedAt' => now(),
        ])->render();

        // اتجاه الملف وخطّه ولغة تذييله من لغة التقرير نفسه.
        $mpdf = $this->engine->make(locale: $report->locale ?: 'ar');
        $mpdf->WriteHTML($html);

        $path = $this->path($report);
        Storage::disk(self::DISK)->put($path, $mpdf->Output('', 'S'));

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();

        return $path;
    }

    public function download(Report $report): StreamedResponse
    {
        $path = $this->ensure($report);
        // اسم الملف بلغة التقرير: من يحمّل تقريرًا فرنسيًّا لا يرتّبه باسم عربي.
        $filename = __('تقرير-:id', ['id' => $report->id], $report->locale ?: 'ar').'.pdf';

        return Storage::disk(self::DISK)->download($path, $filename);
    }

    private function path(Report $report): string
    {
        return sprintf('reports/report-%d-v%d.pdf', $report->id, self::TEMPLATE_VERSION);
    }
}
