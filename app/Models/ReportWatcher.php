<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مراقب التقرير الحي: يربط تقريرًا منشورًا ببصمة مدخلاته يوم التفعيل،
 * ليكتشف الفحص المجدول متى تغيّر ما بُني عليه التقرير فعلًا.
 */
class ReportWatcher extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'report_id', 'project_id', 'user_id', 'status',
        'baseline_fingerprint', 'notified_fingerprint', 'changes',
        'last_checked_at', 'last_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'last_checked_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
