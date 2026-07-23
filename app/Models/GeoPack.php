<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حزمة الظهور للآلات: النسخة القابلة للقراءة الآلية من هوية المشروع،
 * التي تجعله «قابلًا للاستهلاك» من مساعدات الذكاء الاصطناعي لا مرئيًا فقط.
 */
class GeoPack extends Model
{
    protected $fillable = [
        'project_id', 'facts', 'faq', 'jsonld', 'llms_txt',
        'credibility', 'source', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'faq' => 'array',
            'jsonld' => 'array',
            'credibility' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
