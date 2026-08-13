<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateBinding extends Model
{
    protected $fillable = ['template_id', 'field_key', 'answer_key', 'transform'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecommendationTemplate::class, 'template_id');
    }
}
