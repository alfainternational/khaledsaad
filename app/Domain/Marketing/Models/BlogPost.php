<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body_html',
        'featured_image',
        'featured_image_alt',
        'meta_description',
        'og_image',
        'category',
        'tags',
        'reading_time_minutes',
        'author_name',
        'author_title',
        'published_at',
        'is_published',
        'is_featured',
        'sort_order',
        'view_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'is_featured'  => 'boolean',
        'tags'         => 'array',
        'view_count'   => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /** احسب وقت القراءة تلقائياً إذا لم يُحدَّد */
    public function getReadingTimeAttribute(): int
    {
        if ($this->reading_time_minutes) {
            return (int) $this->reading_time_minutes;
        }
        $words = str_word_count(strip_tags($this->body_html ?? ''));
        return max(1, (int) ceil($words / 200));
    }

    /** المؤلف الافتراضي */
    public function getAuthorNameAttribute($value): string
    {
        return $value ?: 'خالد سعد';
    }

    public function getAuthorTitleAttribute($value): string
    {
        return $value ?: 'مدير تسويق ومستشار استراتيجي';
    }

    /** OG Image يرجع featured_image إذا لم يُحدَّد */
    public function getOgImageUrlAttribute(): ?string
    {
        $img = $this->og_image ?: $this->featured_image;
        return $img ? asset('storage/' . $img) : null;
    }

    /** رابط canonical */
    public function getCanonicalUrlAttribute(): string
    {
        return route('blog.show', $this->slug);
    }

    /** مقالات ذات صلة بنفس التصنيف */
    public function related(int $limit = 3)
    {
        return static::query()
            ->published()
            ->where('id', '!=', $this->id)
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /** increment view count */
    public function incrementViews(): void
    {
        $this->incrementQuietly('view_count');
    }
}
