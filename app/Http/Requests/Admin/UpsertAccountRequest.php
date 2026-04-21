<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertAccountRequest extends FormRequest
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
            'owner_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email:rfc', 'max:255'],
            'status' => ['required', Rule::in(['active', 'suspended', 'archived'])],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'subscription_status' => ['nullable', 'required_with:plan_id', Rule::in(['trialing', 'active', 'past_due', 'canceled'])],
            'current_period_end' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan_id' => $this->filled('plan_id') ? (int) $this->input('plan_id') : null,
            'subscription_status' => $this->filled('subscription_status') ? $this->input('subscription_status') : null,
            'current_period_end' => $this->filled('current_period_end') ? $this->input('current_period_end') : null,
        ]);
    }
}
