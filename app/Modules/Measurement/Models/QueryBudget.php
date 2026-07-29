<?php

namespace App\Modules\Measurement\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ميزانية استعلامات شهر واحد لمساحة عمل واحدة.
 */
class QueryBudget extends Model
{
    /** النسبة التي يُنبَّه عندها صاحب المساحة (§٤.٤). */
    public const WARN_AT = 0.8;

    protected $fillable = [
        'workspace_id', 'period', 'monthly_limit',
        'reserved', 'consumed', 'cost_usd', 'warned_at',
    ];

    protected function casts(): array
    {
        return [
            'monthly_limit' => 'integer',
            'reserved' => 'integer',
            'consumed' => 'integer',
            'cost_usd' => 'float',
            'warned_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(QueryReservation::class);
    }

    /**
     * ما التُزم به: المحجوز والمستهلك معًا.
     *
     * الحدّ يُقاس على هذا لا على المستهلك وحده. حجزٌ في الطابور التزامٌ فعليّ
     * بالإنفاق، ومقارنة المستهلك بالحد تسمح لعشرة Jobs بالمرور معًا ثم تكتشف
     * التجاوز بعد أن دُفع ثمنه.
     */
    public function committed(): int
    {
        return $this->reserved + $this->consumed;
    }

    public function remaining(): int
    {
        return max(0, $this->monthly_limit - $this->committed());
    }

    public function usageRatio(): float
    {
        return $this->monthly_limit > 0
            ? $this->committed() / $this->monthly_limit
            : 1.0;
    }

    public function shouldWarn(): bool
    {
        return $this->warned_at === null && $this->usageRatio() >= self::WARN_AT;
    }
}
