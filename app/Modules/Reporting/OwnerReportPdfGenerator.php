<?php

namespace App\Modules\Reporting;

use App\Models\AgencyReport;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerReportPdfGenerator
{
    private const DISK = 'local';

    private const TEMPLATE_VERSION = 2;

    public function __construct(private readonly AgencyReportDocumentAdapter $documents) {}

    public function ensure(AgencyReport $report): string
    {
        $path = "owner-reports/owner-report-{$report->id}-v".self::TEMPLATE_VERSION.'.pdf';

        if (Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        $html = view('agency-reports.owner-pdf', [
            'agencyReport' => $report,
            'snapshot' => $this->documents->ownerSnapshot($report),
            'brand' => config('brand'),
        ])->render();

        $mpdf = $this->engine();
        $mpdf->WriteHTML($html);
        Storage::disk(self::DISK)->put($path, $mpdf->Output('', 'S'));

        return $path;
    }

    public function download(AgencyReport $report): StreamedResponse
    {
        return Storage::disk(self::DISK)->download(
            $this->ensure($report),
            "تقرير-مشروعي-{$report->project->slug}-v{$report->version}.pdf",
        );
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
        $mpdf->SetHTMLFooter('<div style="border-top:1px solid #dfe8f5;padding-top:6px;font-size:8pt;color:#5d6b82;text-align:center;">'.e(config('brand.name', 'خالد سعد')).' · تقريرك الخاص · صفحة {PAGENO} من {nbpg}</div>');

        return $mpdf;
    }
}
