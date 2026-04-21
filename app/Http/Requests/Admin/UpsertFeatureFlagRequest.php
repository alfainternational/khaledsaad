<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeatureFlagStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $flagId = $this->route('featureFlag')?->id;

        return [
            'key' => ['required', 'string', 'max:255', Rule::unique('feature_flags', 'key')->ignore($flagId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'module' => ['nullable', 'string', Rule::in(array_keys(config('module_registry')))],
            'status' => ['required', Rule::enum(FeatureFlagStatus::class)],
            'rollout_percentage' => ['required', 'integer', 'between:0,100'],
            'expires_at' => ['nullable', 'date'],
            'audiences' => ['array'],
            'audiences.*.audience_type' => ['required', Rule::in(['plan', 'workspace', 'user'])],
            'audiences.*.audience_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
