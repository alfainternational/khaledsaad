<?php

namespace App\Domain\Tool\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'module',
        'audience_types_json',
        'goal_tags_json',
        'awareness_levels_json',
        'output_type',
        'estimated_minutes',
        'has_guided_mode',
        'has_structured_mode',
        'has_expert_mode',
        'next_actions_json',
        'depends_on_json',
        'feeds_into_json',
        'stage',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'audience_types_json' => 'array',
        'goal_tags_json' => 'array',
        'awareness_levels_json' => 'array',
        'has_guided_mode' => 'boolean',
        'has_structured_mode' => 'boolean',
        'has_expert_mode' => 'boolean',
        'next_actions_json' => 'array',
        'depends_on_json' => 'array',
        'feeds_into_json' => 'array',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(ToolRun::class, 'tool_code', 'code');
    }
}
