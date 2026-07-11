<?php

namespace App\Http\Requests\Web;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateStudioOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * دعم الموبايل: ترجمة project_public_id إلى project_id ضمن مساحة العمل الحالية.
     * لا أثر على الويب (لا يرسل project_public_id).
     */
    protected function prepareForValidation(): void
    {
        $publicId = $this->input('project_public_id');
        if (! $this->filled('project_id') && is_string($publicId) && $publicId !== '') {
            /** @var Workspace|null $workspace */
            $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
            $project = Project::query()
                ->where('workspace_id', $workspace?->id ?? 0)
                ->where('public_id', $publicId)
                ->first();

            if ($project) {
                $this->merge(['project_id' => $project->id]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Workspace|null $workspace */
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        return [
            'template_id' => ['required', 'integer', Rule::exists('ai_templates', 'id')],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0)
                ),
            ],
            'brief' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
