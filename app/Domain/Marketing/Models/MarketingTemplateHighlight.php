<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingTemplateHighlight extends Model
{
    protected $fillable = [
        'title',
        'description',
        'body_html',
        'category',
        'icon_emoji',
        'cta_label',
        'cta_url',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
