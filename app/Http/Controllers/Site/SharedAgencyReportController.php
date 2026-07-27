<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Reports\AgencyReportPdfGenerator;
use App\Services\Reports\AgencyReportSharing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * العرض العام لموجز الوكالة عبر رابط المشاركة — بلا تسجيل دخول.
 *
 * رمز غير صالح أو منتهٍ أو ملغى يعطي 404 لا رسالة تشرح السبب: لا نؤكد
 * لطرف مجهول أن الرابط كان موجودًا يومًا.
 */
class SharedAgencyReportController extends Controller
{
    public function __construct(
        private readonly AgencyReportSharing $sharing,
        private readonly AgencyReportPdfGenerator $pdf,
    ) {}

    public function show(Request $request, string $token): View
    {
        $report = $this->sharing->resolve($token) ?? throw new NotFoundHttpException;
        $this->sharing->record($report, $request);

        /*
         * دليل المالك يُنزع هنا لا في القالب: القالب الحالي لا يعرضه، لكن
         * تمريره أصلًا يجعل تسريبه مسألة سطر يضيفه أحد لاحقًا بحسن نية.
         * ما لا يُرسل لا يُسرَّب.
         */
        $snapshot = $report->snapshot;
        unset($snapshot['owner_guide']);

        return view('agency-reports.shared', [
            'agencyReport' => $report,
            'snapshot' => $snapshot,
            'shareToken' => $token,
        ]);
    }

    public function pdf(Request $request, string $token): StreamedResponse
    {
        $report = $this->sharing->resolve($token) ?? throw new NotFoundHttpException;
        $this->sharing->record($report, $request, 'pdf');

        return $this->pdf->download($report);
    }

    /**
     * نسخة البيانات لفريق التنفيذ — تُقرأ آليًا بدل نسخ الأرقام من PDF.
     */
    public function data(Request $request, string $token): JsonResponse
    {
        $report = $this->sharing->resolve($token) ?? throw new NotFoundHttpException;
        $this->sharing->record($report, $request, 'data');

        return response()->json($this->sharing->dataFile($report));
    }
}
