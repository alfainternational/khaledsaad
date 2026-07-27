<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionVersion extends Model
{
    protected $fillable = ['question_definition_id', 'version', 'user_text', 'help_text', 'why_text', 'answer_type', 'options', 'validation', 'required', 'allow_unknown', 'allow_skip', 'locked_at'];

    protected function casts(): array
    {
        return ['options' => 'array', 'validation' => 'array', 'required' => 'boolean', 'allow_unknown' => 'boolean', 'allow_skip' => 'boolean', 'locked_at' => 'datetime'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(QuestionDefinition::class, 'question_definition_id');
    }

    public function moduleBindings(): HasMany
    {
        return $this->hasMany(ModuleQuestion::class);
    }
}
