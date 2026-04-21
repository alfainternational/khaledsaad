<?php

namespace App\Domain\AI\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIGeneration extends Model
{
    use HasPublicId;

    protected $table = 'ai_generations';

    protected $fillable = [
        'public_id',
        'account_id',
        'workspace_id',
        'project_id',
        'template_id',
        'created_by',
        'inputs_json',
        'output',
        'tokens_used',
        'status',
        'error',
        'ops_review_status',
        'ops_note',
        'ops_tags',
    ];

    protected $casts = [
        'inputs_json' => 'array',
        'ops_tags' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AITemplate::class, 'template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
