<?php

namespace App\Modules\Brain\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لقطة مجمّدة من الدماغ وقت تشخيص معيّن.
 *
 * السبب: الدرجة تُحسب من حالة الدماغ لحظتها. بلا لقطة محفوظة يستحيل لاحقًا
 * الإجابة على «لماذا كانت درجتي ٦٢ الشهر الماضي؟» لأن الحقائق تكون قد تغيّرت.
 * اللقطة هي ما يجعل المقارنة الزمنية والتنبيه صادقين.
 */
class BrainSnapshot extends Model
{
    /** شكل الحمولة الحالي. يُرفَع عند أي تغيير غير متوافق في البنية. */
    public const CURRENT_SCHEMA_VERSION = 1;

    protected $fillable = ['project_id', 'taken_at', 'payload', 'schema_version'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'taken_at' => 'datetime',
            'schema_version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
