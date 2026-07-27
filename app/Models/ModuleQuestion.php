<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleQuestion extends Model
{
    protected $fillable = ['blueprint_module_id', 'question_version_id', 'diagnostic_impact', 'discrimination', 'answer_burden', 'critical', 'show_when', 'follow_up_rules', 'sort_order'];

    protected function casts(): array
    {
        return ['critical' => 'boolean', 'show_when' => 'array', 'follow_up_rules' => 'array'];
    }

    public function blueprintModule(): BelongsTo
    {
        return $this->belongsTo(BlueprintModule::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }
}
