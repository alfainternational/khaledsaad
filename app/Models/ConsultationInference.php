<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationInference extends Model
{
    protected $fillable = ['consultation_session_id', 'key', 'type', 'statement', 'evidence_ids', 'opposing_evidence_ids', 'confidence', 'status'];

    protected function casts(): array
    {
        return ['evidence_ids' => 'array', 'opposing_evidence_ids' => 'array'];
    }
}
