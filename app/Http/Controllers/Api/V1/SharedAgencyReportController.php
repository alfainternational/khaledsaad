<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\AgencyReportPdfGenerator;
use App\Services\Reports\AgencyReportSharing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SharedAgencyReportController extends Controller
{
    public function __construct(
        private readonly AgencyReportSharing $sharing,
        private readonly AgencyReportPdfGenerator $pdf,
    ) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $report = $this->sharing->resolve($token) ?? throw new NotFoundHttpException;
        $this->sharing->record($report, $request, 'api');

        return response()->json([
            'data' => $this->sharing->dataFile($report),
        ]);
    }

    public function pdf(Request $request, string $token): StreamedResponse
    {
        $report = $this->sharing->resolve($token) ?? throw new NotFoundHttpException;
        $this->sharing->record($report, $request, 'api-pdf');

        return $this->pdf->download($report);
    }
}
