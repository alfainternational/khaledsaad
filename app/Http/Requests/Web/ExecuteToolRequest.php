<?php

namespace App\Http\Requests\Web;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteToolRequest extends FormRequest
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
        $projectRequired = $this->route('project') === null
            && $this->route('project_public_id') === null;

        return [
            'project_id' => [
                $projectRequired ? 'required' : 'nullable',
                'integer',
                Rule::exists('projects', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0)
                ),
            ],
            'mode' => ['required', 'string', Rule::in(['guided', 'structured', 'expert'])],
            'brief' => ['nullable', 'string', 'max:2000'],
            'inputs' => ['nullable', 'array'],
            'inputs.*' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
