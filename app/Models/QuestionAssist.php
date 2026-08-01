<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دليل ومقترحات سؤال واحد لمشروع واحد.
 *
 * صفٌّ واحد لكل (مشروع، سطح، سؤال) — يُحدَّث ولا يتكرر. المخرج كلفته حقيقية،
 * والصف المتكرر يعني دفع ثمن المعلومة نفسها مرتين.
 */
class QuestionAssist extends Model
{
    public const SURFACE_CONSULTATION = 'consultation';

    public const SURFACE_TOOL = 'tool';

    /** موجز الوكالة: أسئلة `BriefQuestions` على المشروع مباشرة، بلا تشغيل ولا جلسة. */
    public const SURFACE_AGENCY = 'agency';

    /** ملف المشروع: أسئلة `ProfileQuestions` — الباب الذي يدخل منه أغلب المستخدمين أولًا. */
    public const SURFACE_PROFILE = 'profile';

    protected $fillable = [
        'project_id', 'query_reservation_id', 'surface', 'question_key', 'context_hash',
        'guide', 'suggestions', 'recommended_value', 'recommendation_reason', 'basis',
        'evidence_level', 'provider', 'model', 'cost_usd', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'suggestions' => 'array',
            'basis' => 'array',
            'cost_usd' => 'float',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * هل هذا المخرج ما زال يخصّ السياق الحالي؟
     *
     * البصمة لا العمر: دليل عمره شهر وسياقه لم يتغيّر ما زال صحيحًا، ودليل
     * عمره دقيقة بُني قبل أن يحدّد المستخدم قطاعه صار يتحدث عن نشاط آخر.
     */
    public function matches(string $contextHash): bool
    {
        return $this->context_hash === $contextHash;
    }
}
