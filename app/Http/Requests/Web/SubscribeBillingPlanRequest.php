<?php

namespace App\Http\Requests\Web;

use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeBillingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                'string',
                'max:100',
                Rule::exists('plans', 'code')->where('status', PlanStatus::Active->value),
            ],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual'],
        ];
    }
}
