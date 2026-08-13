<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationTemplate extends Model
{
    protected $fillable = [
        'objective_id', 'kind', 'title', 'body', 'required_context',
        'is_hypothesis', 'locale', 'version', 'active',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'required_context' => 'array',
            'is_hypothesis' => 'boolean',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(Objective::class);
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(TemplateBinding::class, 'template_id');
    }
}
