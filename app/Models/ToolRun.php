<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ToolRun extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    /**
     * أطول مرحلة قِيست عمليًا نحو 30 ثانية، وأبطأ تشغيل كامل نحو خمس دقائق.
     * عشر دقائق بلا تقدم تعني تعطلًا لا بطئًا.
     */
    public const STALE_AFTER_MINUTES = 10;

    protected $fillable = [
        'uuid', 'project_id', 'consultation_session_id', 'tool_version_id', 'user_id', 'guest_session_id', 'status',
        'current_step', 'base_score', 'confidence', 'snapshot', 'failure_reason',
        'attempts', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * المفتاح الأساسي يبقى رقمًا متسلسلًا للأداء، ويُستخدم uuid في الروابط
     * حتى لا يكشف الرابط عدد التشغيلات في المنصة.
     */
    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function consultationSession(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class);
    }

    public function toolVersion(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ToolRunAnswer::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ToolRunStage::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ToolRunFile::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class);
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function answerMap(): array
    {
        return $this->answers->pluck('value_json', 'field_key')->all();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_PARTIAL, self::STATUS_FAILED], true);
    }

    /**
     * تشغيل متوقف فعليًا: مضى وقت أطول من المعقول دون تقدم.
     *
     * السبب العملي: مع طابور database لا يعمل شيء ما لم يكن هناك عامل مشغّل.
     * بدون هذا الفحص يبقى المستخدم أمام «قيد التحليل» إلى الأبد دون أن يعرف
     * أن العطل في التشغيل لا في مشروعه.
     */
    public function isStale(): bool
    {
        if ($this->isTerminal() || $this->status === self::STATUS_DRAFT) {
            return false;
        }

        $lastActivity = $this->stages()->max('completed_at')
            ?? $this->started_at
            ?? $this->updated_at;

        return $lastActivity !== null
            && Carbon::parse($lastActivity)->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function progressPercent(): int
    {
        $stages = $this->stages;

        if ($stages->isEmpty()) {
            return 0;
        }

        $done = $stages->whereIn('status', ['completed', 'skipped'])->count();

        return (int) round($done / $stages->count() * 100);
    }
}
