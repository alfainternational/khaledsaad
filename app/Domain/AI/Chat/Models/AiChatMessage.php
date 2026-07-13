<?php

namespace App\Domain\AI\Chat\Models;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'conversation_id',
        'intelligence_job_id',
        'role',
        'content',
        'status',
        'client_request_id',
        'error_code',
        'error_message',
        'meta_json',
        'completed_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'intelligence_job_id' => 'integer',
        'meta_json' => 'array',
        'completed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }

    public function intelligenceJob(): BelongsTo
    {
        return $this->belongsTo(IntelligenceJob::class, 'intelligence_job_id');
    }
}
