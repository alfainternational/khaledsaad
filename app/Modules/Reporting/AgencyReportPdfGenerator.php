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

    public function __construct(
        private readonly AgencyReportSharing $sharing,
        private readonly ArabicPdfEngine $engine,
    ) {}

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

        $mpdf = $this->engine->make('موجز وكالة');
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
}
