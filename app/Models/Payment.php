<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'workspace_id', 'user_id', 'provider', 'purpose', 'credit_pack_id', 'plan_id',
        'amount', 'currency', 'charged_amount', 'charged_currency', 'credits_granted',
        'status', 'failure_reason', 'external_id', 'external_capture_id', 'meta',
        'paid_at', 'approved_by', 'approved_at',
        'payment_gateway_id', 'idempotency_key', 'refunded_amount', 'cancelled_at',
        'expires_at', 'customer_reference', 'evidence_path',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'charged_amount' => 'float',
            'refunded_amount' => 'float',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function creditPack(): BelongsTo
    {
        return $this->belongsTo(CreditPack::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * دفعة تحويل يدوي معلّقة: لا تُمنح إلا باعتماد آدمن.
     */
    public function awaitsApproval(): bool
    {
        return $this->provider === 'manual' && $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'مدفوع',
            self::STATUS_FAILED => 'فشل',
            self::STATUS_CANCELLED => 'أُلغي',
            default => 'قيد الانتظار',
        };
    }
}
