<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertToolRequest extends FormRequest
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
        $toolId = $this->route('tool')?->id;

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('tools', 'code')->ignore($toolId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'module' => ['nullable', 'string', 'max:255'],
            'stage' => ['required', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'beta', 'hidden'])],
        ];
    }
}
