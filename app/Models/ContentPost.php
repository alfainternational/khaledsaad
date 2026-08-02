<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPost extends Model
{
    public const STAGE_DESIGN = 'design';

    public const STAGE_REVIEW = 'review';

    public const STAGE_REVIEWED = 'ready';

    public const STAGE_PARTIALLY_PUBLISHED = 'partially_published';

    public const STAGE_PUBLISHED = 'published';

    public const STAGE_ARCHIVED = 'archived';

    protected $fillable = [
        'content_plan_id', 'position', 'publish_at', 'pillar', 'title', 'x_content',
        'linkedin_content', 'design_brief', 'publishing_notes', 'alt_text', 'hashtags',
        'requires_design', 'designed_at', 'reviewed_at', 'x_published_at',
        'linkedin_published_at', 'x_reach', 'x_engagement', 'linkedin_reach',
        'linkedin_engagement', 'measured_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'hashtags' => 'array',
            'requires_design' => 'boolean',
            'designed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'x_published_at' => 'datetime',
            'linkedin_published_at' => 'datetime',
            'measured_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentPlan::class, 'content_plan_id');
    }

    public function progressPercent(): int
    {
        $steps = [$this->reviewed_at, $this->x_published_at, $this->linkedin_published_at];

        if ($this->requires_design) {
            array_unshift($steps, $this->designed_at);
        }

        return (int) round((count(array_filter($steps)) / count($steps)) * 100);
    }

    public function workflowStage(): string
    {
        if ($this->archived_at !== null) {
            return self::STAGE_ARCHIVED;
        }

        if ($this->x_published_at !== null && $this->linkedin_published_at !== null) {
            return self::STAGE_PUBLISHED;
        }

        if ($this->x_published_at !== null || $this->linkedin_published_at !== null) {
            return self::STAGE_PARTIALLY_PUBLISHED;
        }

        if ($this->reviewed_at !== null) {
            return self::STAGE_REVIEWED;
        }

        if ($this->designed_at !== null) {
            return self::STAGE_REVIEW;
        }

        return self::STAGE_DESIGN;
    }

    public function stageLabel(): string
    {
        return match ($this->workflowStage()) {
            self::STAGE_REVIEW => 'قيد المراجعة',
            self::STAGE_REVIEWED => 'جاهز للنشر',
            self::STAGE_PARTIALLY_PUBLISHED => 'نُشر جزئيًا',
            self::STAGE_PUBLISHED => 'منشور',
            self::STAGE_ARCHIVED => 'مؤرشف',
            default => 'قيد التصميم',
        };
    }
}
