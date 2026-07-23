<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRunFile extends Model
{
    protected $fillable = [
        'tool_run_id', 'disk', 'path', 'original_name', 'mime_type',
        'size_bytes', 'extraction_status', 'extracted_text',
    ];

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }
}
