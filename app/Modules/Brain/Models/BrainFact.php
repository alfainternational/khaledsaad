<?php

namespace App\Modules\Brain\Models;

use App\Models\Project;
use App\Modules\Shared\Evidence\EvidenceLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حقيقة واحدة يعرفها النظام عن نشاط تجاري.
 *
 * الحقيقة لا تُحدَّث ولا تُحذف: عند تغيّرها تُنشأ حقيقة أحدث ويُشار إليها من
 * القديمة عبر superseded_by. بلا هذا التسلسل لا يمكن معرفة متى تغيّر النشاط،
 * ولا إعادة إنتاج درجة قديمة، ولا التمييز بين «لم يكن يعرف» و«تغيّر رأيه».
 *
 * @property int $project_id
 * @property string $key
 * @property EvidenceLevel $evidence_level
 */
class BrainFact extends Model
{
    protected $fillable = [
        'project_id', 'key', 'value_json', 'value_hash', 'evidence_level',
        'source_module', 'source_reference', 'period', 'metadata',
        'observed_at', 'superseded_by', 'retracted_at', 'retracted_by_module',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'metadata' => 'array',
            'evidence_level' => EvidenceLevel::class,
            'observed_at' => 'datetime',
            'retracted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by');
    }

    /**
     * الحقائق السارية: ما لم يُستبدل ولم يُسحب.
     *
     * السحب ليس حذفًا: الصف يبقى بقيمته، ويخرج من السريان وحده. الفرق بين
     * «لم يُسأل قط» و«أجاب ثم تراجع» يظل مقروءًا في التاريخ.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_by')->whereNull('retracted_at');
    }

    public function isRetracted(): bool
    {
        return $this->retracted_at !== null;
    }

    /**
     * بصمة القيمة، لتمييز «أعاد التأكيد» عن «غيّر إجابته».
     */
    public static function hash(mixed $value): string
    {
        return hash('sha256', (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }
}
