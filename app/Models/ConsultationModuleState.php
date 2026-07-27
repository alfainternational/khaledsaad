<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationModuleState extends Model
{
    protected $fillable = ['consultation_session_id', 'diagnostic_module_id', 'state', 'reason', 'completeness', 'confidence', 'stop_reason'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(DiagnosticModule::class, 'diagnostic_module_id');
    }
}
