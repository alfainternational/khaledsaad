<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgencyReport extends Model
{
    protected $fillable = [
        'uuid', 'project_id', 'consultation_session_id', 'created_by', 'version', 'title', 'status',
        'source_report_ids', 'visibility', 'snapshot', 'generated_at',
        'pdf_path', 'pdf_generated_at',
        'share_token', 'share_created_at', 'share_expires_at', 'share_revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'source_report_ids' => 'array',
            'visibility' => 'array',
            'snapshot' => 'array',
            'generated_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'share_created_at' => 'datetime',
            'share_expires_at' => 'datetime',
            'share_revoked_at' => 'datetime',
        ];
    }

    /**
     * الرمز لا يُسلَّم في أي تسلسل تلقائي: يُعرض فقط داخل رابط المشاركة
     * الذي تبنيه خدمة المشاركة عمدًا.
     *
     * @var array<int, string>
     */
    protected $hidden = ['share_token'];

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            $report->uuid ??= (string) Str::uuid();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function consultationSession(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function views(): HasMany
    {
        return $this->hasMany(AgencyReportView::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
