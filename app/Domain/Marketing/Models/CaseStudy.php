<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class CaseStudy extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'client_name',
        'industry',
        'summary',
        'body_html',
        'cover_image',
        'published_at',
        'is_published',
        'sort_order',
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
