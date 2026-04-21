<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSubscriptionRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'status' => ['required', 'string', Rule::in(['trialing', 'active', 'past_due', 'canceled', 'pending_payment'])],
            'current_period_end' => ['nullable', 'date'],
            'keep_paypal_link' => ['sometimes', 'boolean'],
        ];
    }
}
