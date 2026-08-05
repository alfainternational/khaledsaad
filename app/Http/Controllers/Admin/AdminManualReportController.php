<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ToolRun;
use App\Services\Tools\ManualReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * طابور المراجعة اليدوية: ينزّل الآدمن الإدخالات، يعالجها خارجيًا،
 * ثم يلصق النتيجة فتُركَّب بنفس بنية التقرير التلقائي.
 */
class AdminManualReportController extends Controller
{
    public function __construct(private readonly ManualReportService $manual) {}

    public function index(): View
    {
        $pending = ToolRun::where('delivery_mode', 'manual')
            ->whereIn('status', [ToolRun::STATUS_QUEUED, ToolRun::STATUS_PROCESSING])
            ->with(['project', 'toolVersion.tool'])
            ->latest('id')
            ->get()
            ->map(fn (ToolRun $run) => [
                'uuid' => $run->uuid,
                'tool' => $run->toolVersion->tool->title,
                'project' => $run->project->name,
                'requested_at' => $run->updated_at?->diffForHumans(),
            ])
            ->all();

        $done = ToolRun::where('delivery_mode', 'manual')
            ->where('status', ToolRun::STATUS_COMPLETED)
            ->with(['project', 'toolVersion.tool', 'report'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ToolRun $run) => [
                'tool' => $run->toolVersion->tool->title,
                'project' => $run->project->name,
                'report_id' => $run->report?->id,
                'reviewed_at' => $run->report?->reviewed_at?->diffForHumans(),
            ])
            ->all();

        return view('admin.manual.index', ['pending' => $pending, 'done' => $done]);
    }

    /**
     * حزمة الإدخالات كملف JSON جاهز للصقه في أي أداة خارجية.
     */
    public function export(ToolRun $run): JsonResponse
    {
        $package = $this->manual->exportPackage($run);

        return response()->json($package, 200, [
            'Content-Disposition' => 'attachment; filename="run-'.$run->uuid.'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function show(ToolRun $run): View
    {
        return view('admin.manual.show', [
            'run' => [
                'uuid' => $run->uuid,
                'tool' => $run->toolVersion->tool->title,
                'project' => $run->project->name,
            ],
            'package' => json_encode(
                $this->manual->exportPackage($run),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
        ]);
    }

    /**
     * استيراد النتيجة المعالَجة خارجيًا وتركيبها كتقرير مُراجَع يدويًا.
     */
    public function store(Request $request, ToolRun $run): RedirectResponse
    {
        $data = $request->validate(['payload' => 'required|string']);
        $decoded = json_decode($data['payload'], true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'payload' => 'المحتوى ليس JSON صالحًا. الصق الكائن كاملًا كما أعادته الأداة.',
            ]);
        }

        $report = $this->manual->import($run, $decoded, $request->user());

        AuditLog::write('manual.import', $report, ['run' => $run->uuid]);

        return redirect()->route('admin.manual.index')
            ->with('status', "رُكِّب التقرير #{$report->id} وظهر في حساب العميل موثّقًا أنه مراجَع يدويًا.");
    }
}
