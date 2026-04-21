<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyCommentsRequest extends FormRequest
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
            'comment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'comment_ids.*' => ['integer', Rule::exists('comments', 'id')],
        ];
    }
}
