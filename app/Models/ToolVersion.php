<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ToolVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_id', 'version', 'credit_cost', 'status', 'output_schema',
        'scoring_rules', 'section_plan', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'output_schema' => 'array',
            'scoring_rules' => 'array',
            'section_plan' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ToolField::class)->orderBy('step')->orderBy('sort_order');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    public function toolRuns(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    public function promptFor(string $stage): ?PromptVersion
    {
        return $this->prompts->firstWhere('stage', $stage);
    }

    /**
     * @return Collection<int, Collection<int, ToolField>>
     */
    public function steps(): Collection
    {
        return $this->fields->groupBy('step');
    }

    public function stepCount(): int
    {
        return max(1, (int) $this->fields->max('step'));
    }
}
