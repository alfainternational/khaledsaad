<?php

namespace App\Http\Requests\Web;

use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteOnboardingRequest extends FormRequest
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
        return [
            'account_name' => ['required', 'string', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', Rule::in(['personal', 'team', 'agency'])],
            'persona' => ['required', 'string', Rule::in(array_keys(PersonaCatalog::all()))],
            'awareness_level' => ['required', 'string', Rule::in(array_keys(AwarenessCatalog::all()))],
            'primary_goal' => ['required', 'string', Rule::in(array_keys(GoalCatalog::all()))],
            'recommended_path' => ['nullable', 'string', Rule::in(array_keys(PathCatalog::all()))],
            'audience' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'content_locale' => ['required', 'string', Rule::in(array_keys(ContentLocaleCatalog::all()))],
            'current_challenge' => ['nullable', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'project_name' => ['required', 'string', 'max:255'],
            'project_stage' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
