<?php

namespace App\Modules\Reporting;

use App\Models\AgencyReport;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyReportPdfGenerator
{
    private const DISK = 'local';

    private const TEMPLATE_VERSION = 3;

    public function __construct(private readonly AgencyReportSharing $sharing) {}

    public function ensure(AgencyReport $report): string
    {
        $path = $this->path($report);

        if ($report->pdf_path === $path && Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        $snapshot = $this->sharing->agencySnapshot($report);

        $html = view('agency-reports.pdf', [
            'agencyReport' => $report,
            'snapshot' => $snapshot,
            'brand' => config('brand'),
        ])->render();

        $mpdf = $this->engine();
        $mpdf->WriteHTML($html);
        Storage::disk(self::DISK)->put($path, $mpdf->Output('', 'S'));

        /*
         * إصدار قالب جديد يعني ملفًا جديدًا؛ القديم لم يعد يُشير إليه شيء.
         * حذفه هنا يمنع تراكم ملفات يتيمة في التخزين مع كل ترقية قالب.
         */
        $stale = $report->pdf_path;

        if (is_string($stale) && $stale !== '' && $stale !== $path) {
            Storage::disk(self::DISK)->delete($stale);
        }

        $report->forceFill([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();

        return $path;
    }

    public function download(AgencyReport $report): StreamedResponse
    {
        $path = $this->ensure($report);

        return Storage::disk(self::DISK)->download(
            $path,
            "موجز-وكالة-{$report->project->slug}-v{$report->version}.pdf",
        );
    }

    private function path(AgencyReport $report): string
    {
        return "agency-reports/agency-report-{$report->id}-v".self::TEMPLATE_VERSION.'.pdf';
    }

    private function engine(): Mpdf
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
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, public_path('assets/fonts')],
            'fontdata' => $fontData + [
                'hacentunisia' => ['R' => 'Hacen-Tunisia.ttf', 'useOTL' => 0xFF],
            ],
            'default_font' => 'hacentunisia',
            'margin_top' => 15,
            'margin_bottom' => 18,
            'margin_left' => 13,
            'margin_right' => 13,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->SetHTMLFooter('<div style="border-top:1px solid #dfe8f5;padding-top:6px;font-size:8pt;color:#5d6b82;text-align:center;">'.e(config('brand.name', 'خالد سعد')).' · موجز وكالة · صفحة {PAGENO} من {nbpg}</div>');

        return $mpdf;
    }
}
