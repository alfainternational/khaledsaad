<?php

namespace App\Modules\AiReadiness\Models;

use App\Models\Project;
use App\Modules\Measurement\Models\QueryReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دورة استطلاع واحدة: مجموعة أسئلة × محاولات، بمزوّد ونموذج محدَّدين.
 */
class PresenceRun extends Model
{
    /** الحدّ الأدنى للمحاولات لكل سؤال (§٤.٢: لا قياس من عيّنة واحدة). */
    public const MIN_ATTEMPTS = 3;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id', 'query_reservation_id', 'provider', 'model', 'locale',
        'questions_count', 'attempts_per_question', 'status', 'failure_reason',
        'cost_usd', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'questions_count' => 'integer',
            'attempts_per_question' => 'integer',
            'cost_usd' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(QueryReservation::class, 'query_reservation_id');
    }

    public function probes(): HasMany
    {
        return $this->hasMany(PresenceProbe::class);
    }

    /**
     * هل بلغت هذه الدورة الحدّ الذي يجوز معه نشر رقم؟
     *
     * دورة نجحت فيها محاولتان من ثلاث ليست كافية: المقام المعلن ثلاثة، وعرض
     * النتيجة بمقام مختلف عمّا يقرؤه المستخدم هو الكذب نفسه بأرقام صحيحة.
     */
    public function isPublishable(): bool
    {
        if ($this->questions_count === 0) {
            return false;
        }

        $expected = $this->questions_count * $this->attempts_per_question;

        return $this->attempts_per_question >= self::MIN_ATTEMPTS
            && $this->probes()->where('status', PresenceProbe::STATUS_OK)->count() === $expected;
    }
}
