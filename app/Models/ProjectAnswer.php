<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إجابة محفوظة على مستوى المشروع لا على مستوى أداة واحدة.
 *
 * القاعدة: ما كتبه صاحب المشروع مرة واحدة يبقى معه في كل مكان —
 * في ملف المشروع وفي أي أداة تسأل عن الشيء نفسه.
 */
class ProjectAnswer extends Model
{
    protected $fillable = ['project_id', 'field_key', 'value_json', 'source_tool_key', 'source_run_id'];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function value(): mixed
    {
        return $this->value_json['value'] ?? null;
    }
}
