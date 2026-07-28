<?php

namespace App\Modules\Reporting;

use App\Models\Report;
use App\Services\Growth\NextToolSuggester;
use App\Support\Presentation\ReportPresenter;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
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

        $mpdf = $this->makeEngine();
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
        $filename = 'تقرير-'.$report->id.'.pdf';

        return Storage::disk(self::DISK)->download($path, $filename);
    }

    private function path(Report $report): string
    {
        return sprintf('reports/report-%d-v%d.pdf', $report->id, self::TEMPLATE_VERSION);
    }

    private function makeEngine(): Mpdf
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            /*
             * إيقاف التبديل التلقائي للخط حاسم: mPDF كان يستبدل خط المنصة
             * بخط عربي مدمج عنده (XBRiyaz) فور رؤيته نصًا عربيًا، فيخرج
             * الملف بخط لا يشبه الموقع. الخط الآن هو ملف الموقع نفسه.
             */
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, public_path('assets/fonts')],
            'fontdata' => $fontData + [
                /*
                 * useOTL كامل ضروري لوصل الحروف العربية: بدونه تُرسم منفصلة.
                 * ملاحظة: هذا الملف لا يحمل جدول GPOS لتموضع الحركات، فعلامات
                 * التشكيل الاختيارية (ضمة/كسرة) قد تُزاح قليلًا — والمتصفح
                 * يخفيها بتموضع احتياطي لا يملكه mPDF. النص بلا تشكيل سليم تمامًا.
                 */
                'hacentunisia' => [
                    'R' => 'Hacen-Tunisia.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'hacentunisia',
            'margin_top' => 16,
            'margin_bottom' => 18,
            'margin_left' => 13,
            'margin_right' => 13,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetHTMLFooter('<div style="border-top: 1px solid #dfe8f5; padding-top: 6px; font-size: 8pt; color: #5d6b82; text-align: center;">'.e(config('brand.name', 'خالد سعد')).' — صفحة {PAGENO} من {nbpg}</div>');

        return $mpdf;
    }
}
