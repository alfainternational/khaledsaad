<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSection extends Model
{
    protected $fillable = ['report_id', 'key', 'title', 'content_json', 'sort_order'];

    protected function casts(): array
    {
        return ['content_json' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
