<?php

namespace App\Models;

use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نتيجة شخصية واحدة على إصدار رسالة واحد.
 *
 * كل نتيجة مربوطة بالإصدار الذي قيست عليه لا بالشخصية وحدها، فتبقى
 * الدرجة قابلة للتفسير بعد إنشاء إصدارات لاحقة.
 */
class MessageTestResult extends Model
{
    protected $fillable = [
        'message_test_batch_id', 'message_variant_id', 'persona_key',
        'score', 'evidence_level', 'reaction', 'strength', 'objection',
        'revised_content', 'model_metadata',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'model_metadata' => 'array',
            'evidence_level' => EvidenceLevel::class,
        ];
    }

    /**
     * رد الشخصية الاصطناعية فرضية دائمًا: لا مشترٍ حقيقي قرأ هذه الرسالة.
     * الدرجة مؤشر ترتيب بين الصياغات، لا قياس أداء متوقَّع.
     */
    public function evidenceLevel(): EvidenceLevel
    {
        return $this->evidence_level ?? EvidenceLevel::Inferred;
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MessageTestBatch::class, 'message_test_batch_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MessageVariant::class, 'message_variant_id');
    }
}
