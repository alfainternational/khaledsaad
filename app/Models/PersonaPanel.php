<?php

namespace App\Models;

use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * لوحة الجمهور الاصطناعي: شخصيات ثابتة لكل مشروع تُختبر عليها الرسائل
 * قبل الإنفاق — بديل مجموعات التركيز لمن لا يملك ميزانيتها.
 */
class PersonaPanel extends Model
{
    protected $fillable = ['project_id', 'personas', 'source', 'evidence_level', 'generated_at'];

    protected function casts(): array
    {
        return [
            'personas' => 'array',
            'generated_at' => 'datetime',
            'evidence_level' => EvidenceLevel::class,
        ];
    }

    /**
     * اللوحة فرضية دائمًا مهما كان مصدرها: النموذج يستنتجها من وصف صاحب
     * المشروع، والبديل الحتمي يشتقها من شرائح كتبها هو. لا أحد منها رصد
     * مشتريًا حقيقيًّا، فترقيتها إلى «مقيس» تكذب على من يبني عليها ميزانية.
     */
    public function evidenceLevel(): EvidenceLevel
    {
        return $this->evidence_level ?? EvidenceLevel::Inferred;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(PersonaTest::class)->latest();
    }
}
