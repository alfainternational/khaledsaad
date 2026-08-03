<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    use HasFactory;

    public const TYPE_ARTICLE = 'article';
    public const TYPE_LESSON = 'lesson';
    public const TYPE_LECTURE = 'lecture';
    public const TYPE_COURSE = 'course';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_SUBSCRIBERS = 'subscribers';

    protected $attributes = [
        'type' => self::TYPE_ARTICLE,
        'status' => self::STATUS_DRAFT,
        'access_level' => self::ACCESS_PUBLIC,
    ];

    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',
        'body_json',
        'body_html',
        'cover_image_path',
        'video_url',
        'duration_minutes',
        'status',
        'access_level',
        'published_at',
        'seo_title',
        'seo_description',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'body_json' => 'array',
            'published_at' => 'datetime',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public static function types(): array
    {
        return [
            self::TYPE_ARTICLE,
            self::TYPE_LESSON,
            self::TYPE_LECTURE,
            self::TYPE_COURSE,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_SCHEDULED])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_SCHEDULED], true)
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function isSubscriberOnly(): bool
    {
        return $this->access_level === self::ACCESS_SUBSCRIBERS;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class, 'course_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
