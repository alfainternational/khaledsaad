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

    public function __construct(
        private readonly AgencyReportDocumentAdapter $documents,
        private readonly ArabicPdfEngine $engine,
    ) {}

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

        $mpdf = $this->engine->make('تقريرك الخاص');
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
}
