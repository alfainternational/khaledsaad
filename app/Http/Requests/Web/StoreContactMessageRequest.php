<?php

namespace App\Http\Requests\Web;

use App\Domain\Marketing\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
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
            'message_type' => ['required', Rule::in(array_keys(ContactMessage::typeOptions()))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required_if:message_type,'.ContactMessage::TYPE_GENERAL, 'nullable', 'string', 'max:200'],
            'body' => ['required_if:message_type,'.ContactMessage::TYPE_GENERAL, 'nullable', 'string', 'max:10000'],
            'company_name' => ['required_if:message_type,'.ContactMessage::TYPE_CONSULTATION, 'nullable', 'string', 'max:160'],
            'business_summary' => ['required_if:message_type,'.ContactMessage::TYPE_CONSULTATION, 'nullable', 'string', 'max:1600'],
            'offer' => ['nullable', 'string', 'max:1600'],
            'market' => ['nullable', 'string', 'max:120'],
            'ideal_customer' => ['required_if:message_type,'.ContactMessage::TYPE_CONSULTATION, 'nullable', 'string', 'max:1600'],
            'pain_points' => ['nullable', 'string', 'max:1600'],
            'primary_goal' => ['required_if:message_type,'.ContactMessage::TYPE_CONSULTATION, 'nullable', 'string', 'max:500'],
            'success_metric' => ['nullable', 'string', 'max:500'],
            'timeframe' => ['nullable', 'string', 'max:120'],
            'current_channels' => ['nullable', 'array'],
            'current_channels.*' => ['string', 'max:120'],
            'current_state' => ['nullable', 'string', 'max:1600'],
            'priority' => ['required_if:message_type,'.ContactMessage::TYPE_CONSULTATION, 'nullable', 'string', 'max:500'],
            'budget_range' => ['nullable', 'string', 'max:120'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:120'],
            'additional_context' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
