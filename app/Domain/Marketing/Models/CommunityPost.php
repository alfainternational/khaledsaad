<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityPost extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body_html',
        'author_display_name',
        'published_at',
        'is_published',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
