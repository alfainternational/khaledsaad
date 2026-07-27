<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionDefinition extends Model
{
    protected $fillable = ['key', 'internal_variable', 'sensitivity', 'inferable', 'legacy_tool_field_id'];

    protected function casts(): array
    {
        return ['inferable' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class);
    }

    public function legacyField(): BelongsTo
    {
        return $this->belongsTo(ToolField::class, 'legacy_tool_field_id');
    }
}
