<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyReportView extends Model
{
    protected $fillable = ['agency_report_id', 'channel', 'viewer_hash', 'user_agent', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function agencyReport(): BelongsTo
    {
        return $this->belongsTo(AgencyReport::class);
    }
}
