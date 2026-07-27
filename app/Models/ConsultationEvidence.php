<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationEvidence extends Model
{
    protected $table = 'consultation_evidence';

    protected $fillable = [
        'consultation_session_id', 'consultation_answer_id', 'type', 'source_label',
        'source_locator', 'disk', 'mime_type', 'size_bytes', 'extraction_status',
        'extracted_text', 'sha256', 'confidence', 'metadata', 'observed_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'observed_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class, 'consultation_session_id');
    }
}
