<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultationAnswer extends Model
{
    protected $fillable = ['consultation_session_id', 'question_version_id', 'value_json', 'source', 'confidence', 'period', 'is_unknown', 'is_skipped', 'confirmed_at'];

    protected function casts(): array
    {
        return ['value_json' => 'array', 'is_unknown' => 'boolean', 'is_skipped' => 'boolean', 'confirmed_at' => 'datetime'];
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ConsultationEvidence::class);
    }
}
