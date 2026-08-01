<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUS_TODO = 'todo';

    public const STATUS_DOING = 'doing';

    public const STATUS_DONE = 'done';

    /** لا دليل تنفيذ بعد — المهمة أُنشئت ولم يُطلب تطويرها. */
    public const GUIDE_NONE = 'none';

    /** في الطابور: الطلب أُدخل والمزود لم يردّ بعد. */
    public const GUIDE_PENDING = 'pending';

    /** دليل مطوَّر بالذكاء الاصطناعي على حالة هذا المشروع. */
    public const GUIDE_READY = 'ready';

    /**
     * دليل من الأرضية الحتمية بعد تعذّر المزود.
     *
     * حالة قائمة بذاتها لا تُطوى في ready: المستخدم يستحق أن يعرف أن ما
     * أمامه قالب مأمون لا صياغة على حالته، وإلا صار الفارق بين المصدرين
     * خفيًّا وهو جوهر تدرّج الدليل (§٤.١).
     */
    public const GUIDE_FALLBACK = 'fallback';

    protected $fillable = [
        'project_id', 'recommendation_id', 'owner_id', 'title', 'description',
        'steps', 'worked_example', 'guide', 'guide_status', 'guide_generated_at',
        'status', 'priority', 'impact', 'effort', 'timeframe',
        'due_date', 'reminder_at', 'reminded_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'worked_example' => 'array',
            'guide' => 'array',
            'guide_generated_at' => 'datetime',
            'due_date' => 'date',
            'reminder_at' => 'datetime',
            'reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function guideStatusLabel(): string
    {
        return match ($this->guide_status) {
            self::GUIDE_PENDING => 'يُطوَّر الآن',
            self::GUIDE_READY => 'دليل تنفيذ جاهز',
            self::GUIDE_FALLBACK => 'دليل مبدئي',
            default => 'بلا دليل تنفيذ',
        };
    }

    public function hasGuide(): bool
    {
        return in_array($this->guide_status, [self::GUIDE_READY, self::GUIDE_FALLBACK], true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /**
     * من يُنبَّه على هذه المهمة. قد يكون null لمهام أُنشئت من مسار لا مالك
     * فيه، ولذلك يتحقق كل مُشعِر من وجوده قبل الإرسال.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DOING => 'قيد التنفيذ',
            self::STATUS_DONE => 'منجزة',
            default => 'لم تبدأ',
        };
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->status !== self::STATUS_DONE
            && $this->due_date->isPast();
    }
}
