<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAITemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_super_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('output_contract_json');
        if ($raw === null || $raw === '') {
            $this->merge(['output_contract_json' => null]);

            return;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $this->merge(['output_contract_json' => is_array($decoded) ? $decoded : null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $templateId = $this->route('aiTemplate')?->id;

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('ai_templates', 'code')->ignore($templateId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'prompt_template' => ['required', 'string'],
            'model' => ['required', 'string', 'max:255'],
            'credit_cost' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'module' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'system_role' => ['nullable', 'string', 'max:20000'],
            'output_contract_json' => ['nullable', 'array'],
            'output_contract_json.sections' => ['nullable', 'array'],
            'output_contract_json.sections.*' => ['string', 'max:500'],
            'output_contract_json.quality_rubric' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
