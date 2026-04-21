<?php

namespace App\Http\Requests\Web;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace?->id ?? 0)
                ),
            ],
            'stage' => ['required', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'string', Rule::in(['active', 'paused', 'completed', 'archived'])],
        ];
    }
}
