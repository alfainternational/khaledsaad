<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('plans', 'code')->ignore($planId)],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['nullable', 'numeric', 'min:0'],
            'paypal_plan_id_monthly' => ['nullable', 'string', 'max:255'],
            'paypal_plan_id_annual' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(PlanStatus::class)],
            'entitlements' => ['array'],
            'entitlements.*.key' => ['required', 'string', 'max:255'],
            'entitlements.*.value_type' => ['required', Rule::in(['boolean', 'integer', 'float', 'string', 'json'])],
            'entitlements.*.value' => ['nullable'],
        ];
    }
}
