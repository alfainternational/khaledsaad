<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_run_id', 'project_id', 'title', 'locale', 'status', 'score', 'score_band',
        'summary', 'assumptions', 'next_step', 'generated_by_model', 'tool_version',
        'published_at', 'pdf_path', 'pdf_generated_at',
        'review_mode', 'reviewed_by', 'reviewed_at',
        'provenance', 'score_raw', 'score_max', 'issued_at', 'authored_at',
        'authored_by', 'validation_status', 'schema_version', 'contract_payload',
        'declared_gaps',
    ];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'declared_gaps' => 'array',
            'next_step' => 'array',
            'published_at' => 'datetime',
            'issued_at' => 'datetime',
            'authored_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'score_raw' => 'float',
            'score_max' => 'float',
            'schema_version' => 'integer',
            'contract_payload' => 'array',
        ];
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReportSection::class)->orderBy('sort_order');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('sort_order');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderByDesc('priority');
    }

    /**
     * الفصل الصريح بين ما يستند إلى دليل وما هو افتراض — قاعدة BR-007.
     */
    public function evidenceBackedFindings(): HasMany
    {
        return $this->findings()->where('is_assumption', false);
    }

    public function assumedFindings(): HasMany
    {
        return $this->findings()->where('is_assumption', true);
    }

    /**
     * مراقب التقرير الحي — وجوده يعني أن المستخدم فعّل المتابعة المستمرة.
     */
    public function watcher(): HasOne
    {
        return $this->hasOne(ReportWatcher::class);
    }

    public function feedback(): MorphMany
    {
        return $this->morphMany(ContentFeedback::class, 'subject');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class);
    }

    public function validationFindings(): HasMany
    {
        return $this->hasMany(ValidationFinding::class);
    }

    public function humanTraces(): HasMany
    {
        return $this->hasMany(HumanTrace::class);
    }

    public function scoringItems(): HasMany
    {
        return $this->hasMany(ScoringItem::class);
    }

    public static function bandFor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'ناضج',
            $score >= 60 => 'مستقر',
            $score >= 40 => 'يحتاج ترتيبًا',
            default => 'مبعثر',
        };
    }
}
