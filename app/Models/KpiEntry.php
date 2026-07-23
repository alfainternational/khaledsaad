<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEntry extends Model
{
    protected $fillable = ['kpi_id', 'value', 'recorded_at', 'source'];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'date',
        ];
    }

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }
}
