<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AgencyReport;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\ToolRun;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesWorkspace
{
    protected function authorizeProject(Request $request, Project $project): Project
    {
        return $this->assert($request, $project);
    }

    protected function authorizeRun(Request $request, ToolRun $run): ToolRun
    {
        return $this->assert($request, $run);
    }

    protected function authorizeReport(Request $request, Report $report): Report
    {
        return $this->assert($request, $report);
    }

    protected function authorizeAgencyReport(Request $request, AgencyReport $report): AgencyReport
    {
        $this->assert($request, $report->project);

        return $report;
    }

    protected function authorizeTask(Request $request, Task $task): Task
    {
        return $this->assert($request, $task);
    }

    /**
     * 404 بدل 403: لا نؤكد وجود المورد أصلًا لمن لا يملكه.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  T  $model
     * @return T
     */
    private function assert(Request $request, $model)
    {
        if ($request->user()?->can('view', $model) !== true) {
            throw new NotFoundHttpException;
        }

        return $model;
    }
}
