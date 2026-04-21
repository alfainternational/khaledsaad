<?php

namespace App\Http\Requests\Admin;

use App\Domain\Workspace\Enums\WorkspaceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_super_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(WorkspaceType::class)],
            'status' => ['required', Rule::in(['active', 'paused', 'archived'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'owner_user_id' => $this->filled('owner_user_id') ? (int) $this->input('owner_user_id') : null,
        ]);
    }
}
