<?php

namespace App\Models;

use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaTest extends Model
{
    protected $fillable = ['persona_panel_id', 'user_id', 'message', 'results', 'evidence_level'];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'evidence_level' => EvidenceLevel::class,
        ];
    }

    public function evidenceLevel(): EvidenceLevel
    {
        return $this->evidence_level ?? EvidenceLevel::Inferred;
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(PersonaPanel::class, 'persona_panel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
