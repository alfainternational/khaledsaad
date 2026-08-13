<?php

namespace App\Console\Commands;

use App\Models\AgencyReport;
use App\Models\Report;
use App\Models\ToolRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetTrialReports extends Command
{
    protected $signature = 'reports:trial-reset
        {--execute : نفّذ التنظيف بدل المعاينة}
        {--backup= : مجلد موجود وقابل للكتابة لحفظ النسخة الاحتياطية}
        {--production-confirmation= : اكتب RESET-TRIAL-REPORTS للإنتاج}
        {--include-completed-runs : احذف التشغيلات المكتملة التي كان لها تقرير}';

    protected $description = 'معاينة أو تنظيف نطاق التقارير التجريبية مع نسخة احتياطية إلزامية.';

    public function handle(): int
    {
        $counts = [
            'reports' => Report::count(),
            'agency_reports' => AgencyReport::count(),
        ];

        if (! $this->option('execute')) {
            $this->info('معاينة فقط؛ لم تُحذف أي بيانات.');
            $this->table(['النطاق', 'العدد'], collect($counts)->map(fn ($count, $name) => [$name, $count])->values()->all());

            return self::SUCCESS;
        }

        $backup = $this->option('backup');
        if (! is_string($backup) || $backup === '' || ! is_dir($backup) || ! is_writable($backup)) {
            $this->error('التنفيذ يتطلب --backup لمسار موجود وقابل للكتابة.');

            return self::FAILURE;
        }

        if (app()->environment('production') && $this->option('production-confirmation') !== 'RESET-TRIAL-REPORTS') {
            $this->error('بيئة الإنتاج تتطلب --production-confirmation=RESET-TRIAL-REPORTS.');

            return self::FAILURE;
        }

        $reportIds = Report::query()->pluck('id')->all();
        $runIds = Report::query()->pluck('tool_run_id')->all();
        $agencyIds = AgencyReport::query()->pluck('id')->all();
        $reportPdfs = Report::query()->whereNotNull('pdf_path')->pluck('pdf_path')->filter()->values()->all();
        $agencyPdfs = AgencyReport::query()->whereNotNull('pdf_path')->pluck('pdf_path')->filter()->values()->all();
        $pdfFiles = $this->backupPdfFiles([...$reportPdfs, ...$agencyPdfs], $backup);
        if ($pdfFiles === null) {
            return self::FAILURE;
        }
        $payload = [
            'created_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'counts' => $counts,
            'report_ids' => $reportIds,
            'tool_run_ids' => $runIds,
            'agency_report_ids' => $agencyIds,
            'pdf_files' => $pdfFiles,
            'reports' => Report::query()->with([
                'sections',
                'findings.recommendations',
                'validationFindings',
                'revisions',
                'humanTraces',
                'scoringItems',
                'watcher',
                'feedback',
            ])->get()->toArray(),
            'agency_reports' => AgencyReport::query()->with('views')->get()
                ->each->makeVisible('share_token')
                ->toArray(),
        ];
        $file = rtrim($backup, '\\/').DIRECTORY_SEPARATOR.'trial-reports-'.now()->format('Ymd-His').'.json';
        if (file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
            $this->error('تعذر كتابة ملف النسخة الاحتياطية.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($runIds): void {
            Report::query()->with('feedback')->each(function (Report $report): void {
                $report->feedback()->delete();
            });
            AgencyReport::query()->delete();
            Report::query()->delete();

            if ($this->option('include-completed-runs')) {
                ToolRun::query()->whereIn('id', $runIds)->whereIn('status', ['completed', 'partial'])->delete();
            }
        });

        foreach ([...$reportPdfs, ...$agencyPdfs] as $path) {
            Storage::delete($path);
        }

        $this->info('اكتمل تنظيف نطاق التقارير التجريبية. النسخة الاحتياطية: '.$file);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{source:string,backup:?string,status:string}>|null
     */
    private function backupPdfFiles(array $paths, string $backupRoot): ?array
    {
        $manifest = [];
        $filesRoot = rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.'files';

        foreach (array_values(array_unique($paths)) as $path) {
            if (! Storage::exists($path)) {
                $manifest[] = ['source' => $path, 'backup' => null, 'status' => 'missing'];

                continue;
            }

            if (! is_dir($filesRoot) && ! mkdir($filesRoot, 0777, true) && ! is_dir($filesRoot)) {
                $this->error('تعذر إنشاء مجلد ملفات النسخة الاحتياطية.');

                return null;
            }

            $safeName = hash('sha256', $path).'-'.basename(str_replace('\\', '/', $path));
            $target = $filesRoot.DIRECTORY_SEPARATOR.$safeName;
            if (! copy(Storage::path($path), $target)) {
                $this->error('تعذر نسخ ملف PDF إلى النسخة الاحتياطية: '.$path);

                return null;
            }

            $manifest[] = [
                'source' => $path,
                'backup' => 'files/'.$safeName,
                'status' => 'copied',
            ];
        }

        return $manifest;
    }
}
