<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiGenerationOpsRequest extends FormRequest
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
            'ops_review_status' => ['nullable', 'string', Rule::in(['open', 'investigating', 'resolved', 'voided'])],
            'ops_note' => ['nullable', 'string', 'max:5000'],
            'ops_tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}
