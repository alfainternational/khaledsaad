<?php

namespace App\Domain\AI\Worker\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceJob extends Model
{
    protected $fillable = [
        'public_id',
        'account_id',
        'workspace_id',
        'project_id',
        'intelligence_worker_id',
        'type',
        'status',
        'lease_token_hash',
        'payload_json',
        'input_hash',
        'result_json',
        'output_hash',
        'model_name',
        'model_version',
        'attempts',
        'timeout_seconds',
        'max_attempts',
        'progress',
        'available_at',
        'lease_started_at',
        'leased_until',
        'completed_at',
        'last_error',
    ];

    protected $hidden = ['lease_token_hash'];

    protected $casts = [
        'account_id' => 'integer',
        'workspace_id' => 'integer',
        'project_id' => 'integer',
        'intelligence_worker_id' => 'integer',
        'payload_json' => 'array',
        'result_json' => 'array',
        'attempts' => 'integer',
        'timeout_seconds' => 'integer',
        'max_attempts' => 'integer',
        'progress' => 'integer',
        'available_at' => 'datetime',
        'lease_started_at' => 'datetime',
        'leased_until' => 'datetime',
        'completed_at' => 'datetime',
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

    public function worker(): BelongsTo
    {
        return $this->belongsTo(IntelligenceWorker::class, 'intelligence_worker_id');
    }
}
