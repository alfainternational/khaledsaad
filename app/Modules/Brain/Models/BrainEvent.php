<?php

namespace App\Modules\Brain\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حدث وقع للنشاط، ونتيجته إن عُرفت.
 *
 * هذا ما يحوّل الدماغ من سجل حقائق إلى ذاكرة تجربة: الحقيقة تقول «ميزانيته
 * ١٠ آلاف»، والحدث يقول «جرّب حملة بحث ولم تنجح». النتيجة تُملأ لاحقًا لأن
 * الحدث يُسجَّل عند وقوعه وتُعرف نتيجته بعد حين.
 */
class BrainEvent extends Model
{
    /** تعارض بين مصدرين على قيمة واحدة — يُعلَّم للمراجعة ولا يُحسم صامتًا. */
    public const TYPE_FACT_CONFLICT = 'fact_conflict';

    /** حقيقة استُبدلت بأحدث منها. */
    public const TYPE_FACT_SUPERSEDED = 'fact_superseded';

    /** حقيقة سُحبت: تراجَع المستخدم أو زال مصدرها. */
    public const TYPE_FACT_RETRACTED = 'fact_retracted';

    /**
     * درجة نضج حُسبت، مربوطة باللقطة التي حُسبت منها.
     *
     * هنا يسكن تاريخ `maturity_score`. لا جدول ثالث له: السلسلة الزمنية
     * أحداثٌ وقعت للنشاط، وربطها بلقطتها هو ما يجعل «كانت ٦٢» قابلة للإثبات
     * لا مجرد رقم محفوظ.
     */
    public const TYPE_DIAGNOSIS_SCORED = 'diagnosis_scored';

    protected $fillable = ['project_id', 'type', 'body', 'outcome', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
