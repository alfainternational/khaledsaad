<?php

namespace App\Http\Requests\Web;

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
