<?php

namespace App\Http\Resources\V1;

use App\Domain\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'features' => $this->features_json ?? [],
        ];
    }
}
