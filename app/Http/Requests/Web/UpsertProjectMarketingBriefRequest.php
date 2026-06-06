<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpsertProjectMarketingBriefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business.summary' => ['nullable', 'string', 'max:1200'],
            'business.offer' => ['nullable', 'string', 'max:1000'],
            'business.market' => ['nullable', 'string', 'max:255'],
            'audience.ideal_customer' => ['nullable', 'string', 'max:1000'],
            'audience.pain_points' => ['nullable', 'string', 'max:1200'],
            'audience.buying_trigger' => ['nullable', 'string', 'max:800'],
            'goals.primary_goal' => ['nullable', 'string', 'max:255'],
            'goals.success_metric' => ['nullable', 'string', 'max:255'],
            'goals.timeframe' => ['nullable', 'string', 'max:255'],
            'current_marketing.channels' => ['nullable', 'string', 'max:500'],
            'current_marketing.current_state' => ['nullable', 'string', 'max:1000'],
            'current_marketing.assets' => ['nullable', 'string', 'max:1000'],
            'brand.voice' => ['nullable', 'string', 'max:255'],
            'brand.tone_rules' => ['nullable', 'string', 'max:1000'],
            'positioning.edge' => ['nullable', 'string', 'max:1000'],
            'positioning.promise' => ['nullable', 'string', 'max:1000'],
            'competition.competitors' => ['nullable', 'string', 'max:1000'],
            'competition.gap' => ['nullable', 'string', 'max:1000'],
            'execution.priority' => ['nullable', 'string', 'max:1000'],
            'execution.next_asset' => ['nullable', 'string', 'max:255'],
            'execution.delivery_notes' => ['nullable', 'string', 'max:1000'],
            'commercial.budget_range' => ['nullable', 'string', 'max:255'],
            'commercial.decision_maker' => ['nullable', 'string', 'max:255'],
        ];
    }
}
