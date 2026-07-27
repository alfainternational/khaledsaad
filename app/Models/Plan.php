<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'key', 'name', 'interval', 'price', 'monthly_credits',
        'project_limit', 'features', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * عناصر الميزات المختارة لهذه الخطة بأعدادها.
     *
     * العمود القديم features (نصوص حرّة) يبقى للعرض في الخطط التي لم تُحوَّل
     * بعد، لكنه لا يحكم شيئًا. الحكم من هنا.
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function featureItems(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['enabled', 'value', 'note', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * هل صارت الخطة محكومة بعناصر حقيقية؟ الخطة التي لم يُحدَّد لها أي عنصر
     * تُعامَل كخطة قديمة: لا نمنع عنها شيئًا حتى يضبطها الآدمن.
     */
    public function isGoverned(): bool
    {
        return $this->planFeatures()->exists();
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }
}
