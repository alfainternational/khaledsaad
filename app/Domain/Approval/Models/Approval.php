<?php

namespace App\Domain\Approval\Models;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'workspace_id',
        'project_id',
        'item_type',
        'item_id',
        'status',
        'reviewer_id',
        'note',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'item_id');
    }

    public function aiGeneration(): BelongsTo
    {
        return $this->belongsTo(AIGeneration::class, 'item_id');
    }

    public function executionPackage(): BelongsTo
    {
        return $this->belongsTo(ExecutionPackage::class, 'item_id');
    }
}
