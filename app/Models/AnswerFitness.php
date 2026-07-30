<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * درجة كفاية إجابة واحدة عن حقيقة واحدة.
 *
 * وجودها هو ما يفرّق بين «أجاب» و«أجاب بما يكفي». قبلها كانت أي إجابة غير
 * فارغة تُحسب مدخلًا كاملًا، فيستوي من كتب «الجميع» بمن وصف ثلاث شرائح
 * بأرقامها — والدرجة الناتجة تطمئن صاحب النشاط على أضعف ما عنده.
 */
class AnswerFitness extends Model
{
    /**
     * اسم صريح: التصريف التلقائي يعطي `answer_fitnesses`، وهو خطأ لغوي
     * واسم جدول لا يقابل شيئًا في الهجرة.
     */
    protected $table = 'answer_fitness';

    public const SOURCE_DETERMINISTIC = 'deterministic';

    public const SOURCE_ASSIST = 'assist';

    /** كافية: تدخل حساب المحور بكامل وزنها. */
    public const VERDICT_SUFFICIENT = 'sufficient';

    /** مقبولة وتحتاج تحديدًا: تدخل بجزء من وزنها وتظهر في الفجوات. */
    public const VERDICT_PARTIAL = 'partial';

    /** غير كافية: موجودة شكلًا وغائبة معنًى. */
    public const VERDICT_INSUFFICIENT = 'insufficient';

    protected $fillable = [
        'project_id', 'field_key', 'score', 'verdict', 'gaps', 'basis',
        'value_fingerprint', 'source', 'evidence_level', 'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'gaps' => 'array',
            'basis' => 'array',
            'scored_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isSufficient(): bool
    {
        return $this->verdict === self::VERDICT_SUFFICIENT;
    }

    /**
     * معامل الجودة الذي يُضرب في وزن المدخل عند حساب المحور.
     *
     * لا يهبط إلى الصفر أبدًا: إجابة ضعيفة ليست غيابًا. من أجاب بشيء أعطى
     * أكثر من الذي لم يجب، وتصفيرها يجعل الاثنين سواءً فيختفي الحافز على
     * الإجابة أصلًا. والحدّ الأدنى معلن هنا لا مبثوث في الحساب.
     */
    public function factor(): float
    {
        return max(0.35, min(1.0, $this->score / 100));
    }
}
