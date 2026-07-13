<?php

namespace App\Domain\AI\Chat\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatConversation extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'account_id',
        'workspace_id',
        'user_id',
        'project_id',
        'title',
        'tool_key',
        'last_message_at',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'workspace_id' => 'integer',
        'user_id' => 'integer',
        'project_id' => 'integer',
        'last_message_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id');
    }
}
