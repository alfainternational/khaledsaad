<?php

namespace App\Domain\Tool\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolRun extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'workspace_id',
        'project_id',
        'tool_code',
        'mode',
        'inputs_json',
        'output_json',
        'summary_json',
        'next_actions_json',
        'source_context_json',
        'completeness_score',
        'created_by',
        'ops_review_status',
        'ops_note',
        'ops_tags',
    ];

    protected $casts = [
        'inputs_json' => 'array',
        'output_json' => 'array',
        'summary_json' => 'array',
        'next_actions_json' => 'array',
        'source_context_json' => 'array',
        'ops_tags' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_code', 'code');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
