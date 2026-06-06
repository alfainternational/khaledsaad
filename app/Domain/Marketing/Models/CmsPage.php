<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'body_html',
        'meta_description',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public static function publishedBySlug(string $slug): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }
}
