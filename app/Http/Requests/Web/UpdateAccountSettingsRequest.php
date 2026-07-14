<?php

namespace App\Http\Requests\Web;

use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\Dashboard\PersonaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(['ar', 'en'])],
            'account_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', Rule::in(['personal', 'team', 'agency'])],
            'persona' => ['required', 'string', Rule::in(array_keys(PersonaCatalog::all()))],
            'awareness_level' => ['required', 'string', Rule::in(array_keys(AwarenessCatalog::all()))],
            'primary_goal' => ['required', 'string', Rule::in(array_keys(GoalCatalog::all()))],
            'recommended_path' => ['nullable', 'string', Rule::in(array_keys(PathCatalog::all()))],
            'audience' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:120'],
            'content_locale' => ['required', 'string', Rule::in(array_keys(ContentLocaleCatalog::all()))],
            'current_challenge' => ['nullable', 'string', 'max:255'],
        ];
    }
}
