<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * تغذية راجعة على مخرج مولّد (تقرير اليوم، وأي كيان آخر لاحقًا).
 * هذه إشارة التعلّم: ما يُقيَّم سلبًا يُراجع، وما يُقيَّم إيجابًا يُحتذى.
 */
class ContentFeedback extends Model
{
    public const VERDICT_UP = 'up';

    public const VERDICT_DOWN = 'down';

    protected $table = 'content_feedback';

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'verdict', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
