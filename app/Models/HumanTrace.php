<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HumanTrace extends Model
{
    protected $fillable = ['report_id', 'finding_id', 'type', 'body', 'meta', 'created_by'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
