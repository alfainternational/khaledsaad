<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Domain\Project\Models\Project;

/**
 * يحل المشروع الحالي من project_id الذي يحقنه middleware api.project.
 */
trait ResolvesCurrentProject
{
    protected function currentProject(): Project
    {
        return Project::query()->findOrFail(request()->input('project_id'));
    }
}
