<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentPlan extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id', 'project_id', 'title', 'month', 'status', 'source_filename',
        'design_specifications', 'publishing_specifications', 'activity_protocol', 'safety_rules',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'design_specifications' => 'array',
            'publishing_specifications' => 'array',
            'activity_protocol' => 'array',
            'safety_rules' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ContentPost::class)->orderBy('position');
    }

    public function progressPercent(): int
    {
        $posts = $this->relationLoaded('posts') ? $this->posts : $this->posts()->get();

        if ($posts->isEmpty()) {
            return 0;
        }

        return (int) round($posts->avg(fn (ContentPost $post) => $post->progressPercent()));
    }
}
