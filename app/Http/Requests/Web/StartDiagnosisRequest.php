<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StartDiagnosisRequest extends FormRequest
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
            'input_url' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'case_type' => ['required', 'string', 'in:website,social,project,competitors'],
            'goal' => ['nullable', 'string', 'max:60'],
            'competitor' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (blank($this->input('input_url')) && blank($this->input('business_name'))) {
                $validator->errors()->add('input_url', 'أدخل رابط الموقع أو اسم النشاط على الأقل.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'case_type.required' => 'اختر نوع الحالة.',
            'case_type.in' => 'نوع الحالة غير صالح.',
        ];
    }
}
