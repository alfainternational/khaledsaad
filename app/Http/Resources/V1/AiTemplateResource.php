<?php

namespace App\Http\Resources\V1;

use App\Domain\AI\Models\AITemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AITemplate
 */
class AiTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'module' => $this->module,
            'credit_cost' => $this->credit_cost,
            'status' => $this->status,
        ];
    }
}
