<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ConvertContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'exists:workspaces,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'project_stage' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
