<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = [
        'name',
        'logo_path',
        'description',
        'website_url',
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
