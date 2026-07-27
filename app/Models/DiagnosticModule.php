<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticModule extends Model
{
    protected $fillable = ['key', 'name', 'tool_id', 'applicability', 'sort_order'];

    protected function casts(): array
    {
        return ['applicability' => 'array'];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }
}
