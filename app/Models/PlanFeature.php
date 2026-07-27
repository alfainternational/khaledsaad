<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * اختيار الخطة لعنصر ميزة + عدده. مصدر الحقيقة للاستحقاق.
 */
class PlanFeature extends Model
{
    protected $fillable = ['plan_id', 'feature_id', 'enabled', 'value', 'note', 'sort_order'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
