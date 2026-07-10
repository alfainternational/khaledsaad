<?php

namespace App\Http\Resources\V1;

use App\Domain\Project\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * النسخة التفصيلية لمشروع واحد — تضيف الحقول الغنية وسياق الرحلة والتدقيق.
 * الحقول الإضافية (brief_assessment/journey_snapshot/readiness/latest_audit)
 * تُحقن من الـ controller كسمات مُحسوبة عند توفّرها.
 *
 * @mixin Project
 */
class ProjectDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'name' => $this->name,
            'stage' => $this->stage,
            'status' => $this->status,
            'sector' => $this->sector,
            'market_country' => $this->market_country,
            'primary_domain' => $this->primary_domain,
            'monitoring_enabled' => (bool) $this->monitoring_enabled,
            'official_social_links' => $this->official_social_links_json ?? [],
            'verified_social_profiles' => $this->verified_social_profiles_json ?? [],
            'competitors' => $this->competitors_json ?? [],
            'analysis_goals' => $this->analysis_goals_json ?? [],
            'client' => new ClientResource($this->whenLoaded('client')),
            'brief_assessment' => $this->whenHas('brief_assessment'),
            'journey_snapshot' => $this->whenHas('journey_snapshot'),
            'readiness' => $this->whenHas('readiness'),
            'latest_audit' => $this->whenHas('latest_audit'),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
