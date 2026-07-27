<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationEvent extends Model
{
    protected $fillable = ['consultation_session_id', 'name', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
