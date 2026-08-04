<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    public static function icons(): array
    {
        return [
            'folder',
            'megaphone',
            'book-open',
            'graduation-cap',
            'presentation',
            'chart',
            'lightbulb',
            'target',
        ];
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $attributes = [
        'icon' => 'folder',
        'color' => '#2575ff',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
