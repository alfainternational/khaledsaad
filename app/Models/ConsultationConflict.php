<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationConflict extends Model
{
    protected $fillable = ['consultation_session_id', 'key', 'severity', 'message', 'subject', 'status', 'resolution', 'resolved_at'];

    protected function casts(): array
    {
        return ['subject' => 'array', 'resolution' => 'array', 'resolved_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class, 'consultation_session_id');
    }
}
