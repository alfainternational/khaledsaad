<?php

namespace App\Modules\Measurement\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حجز مواضع استعلام قبل إدخال المهمة إلى الطابور.
 *
 * الحجز يمرّ بحالة واحدة من ثلاث ولا يعود:
 *   held      محجوز وينتظر التنفيذ.
 *   consumed  نُفِّذ، وسُجِّلت تكلفته الفعلية.
 *   released  أُلغي أو فشل المزوّد فأُعيد الموضع.
 */
class QueryReservation extends Model
{
    public const STATUS_HELD = 'held';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'query_budget_id', 'project_id', 'purpose',
        'queries', 'status', 'cost_usd', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'queries' => 'integer',
            'cost_usd' => 'float',
            'settled_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(QueryBudget::class, 'query_budget_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_HELD;
    }
}
