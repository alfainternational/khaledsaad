<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\Presentation\ReportPresenter;
use Illuminate\View\View;

/**
 * تقرير للقراءة فقط برابط موقّع مؤقت (بند ١٨ من خطة الواجهات).
 *
 * صاحب المشروع يريد أن يُري شريكه أو مموّله تقريره دون حساب. الرابط
 * يولَّد من صفحة التقرير بصلاحية ٧ أيام؛ التوقيع يتحقق منه middleware
 * signed فلا يُخمَّن ولا يُعدَّل. القراءة فقط: لا أزرار تحويل ولا مهام.
 */
class SharedReportController extends Controller
{
    public function __construct(private readonly ReportPresenter $presenter) {}

    public function show(Report $report): View
    {
        abort_unless($report->status === 'published', 404);

        return view('reports.shared', [
            'report' => $this->presenter->full($report),
            'comparison' => $this->presenter->comparison($report, $this->presenter->previousFor($report)),
        ]);
    }
}
