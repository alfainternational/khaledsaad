<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FeatureFlagStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchFeatureFlagRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(FeatureFlagStatus::class)],
            'rollout_percentage' => ['sometimes', 'integer', 'between:0,100'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
