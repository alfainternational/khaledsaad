<?php

use App\Domain\Project\Models\Project;
use App\Jobs\CaptureMonitoringSnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('intelligence:monitoring-snapshots {cadence=weekly}', function (string $cadence): void {
    Project::query()
        ->where('monitoring_enabled', true)
        ->orderBy('id')
        ->chunkById(100, function ($projects): void {
            foreach ($projects as $project) {
                CaptureMonitoringSnapshotJob::dispatch($project->id);
            }
        });

    $this->info(sprintf('Queued monitoring snapshots for cadence: %s', $cadence));
})->purpose('Queue monitoring snapshots for projects with intelligence monitoring enabled.');

Schedule::command('intelligence:monitoring-snapshots weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->name('intelligence-weekly-monitoring');

Schedule::command('intelligence:monitoring-snapshots monthly')
    ->monthlyOn(1, '09:00')
    ->withoutOverlapping()
    ->name('intelligence-monthly-monitoring');
