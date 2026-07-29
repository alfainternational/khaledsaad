<?php

namespace App\Modules\AiReadiness\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * محاولة واحدة على سؤال واحد — الوحدة الذرّية للقياس.
 */
class PresenceProbe extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'presence_run_id', 'question_key', 'question', 'attempt',
        'brand_mentioned', 'site_cited', 'brands_mentioned', 'citations',
        'raw_response', 'latency_ms', 'status',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'brand_mentioned' => 'boolean',
            'site_cited' => 'boolean',
            'brands_mentioned' => 'array',
            'citations' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PresenceRun::class, 'presence_run_id');
    }
}
