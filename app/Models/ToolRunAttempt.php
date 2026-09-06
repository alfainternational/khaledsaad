<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * محاولة تشغيل واحدة — بما فيها الفاشلة.
 *
 * وجودها يجيب على سؤالٍ لم يكن له جواب: «لماذا تأخّر تقرير هذا العميل؟».
 * قبلها كانت `failure_reason` تحمل آخر عطلٍ فقط، فتُمحى قصة المحاولات
 * التي سبقته — وهي القصة التي تكشف مزوّدًا يتذبذب لا يسقط.
 */
class ToolRunAttempt extends Model
{
    public const UPDATED_AT = null;

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEFERRED = 'deferred';

    protected $fillable = [
        'tool_run_id', 'attempt', 'provider', 'status',
        'failure_kind', 'error_class', 'error_detail', 'duration_ms',
    ];

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }
}
