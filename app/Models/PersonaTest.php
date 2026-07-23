<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonaTest extends Model
{
    protected $fillable = ['persona_panel_id', 'user_id', 'message', 'results'];

    protected function casts(): array
    {
        return [
            'results' => 'array',
        ];
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
