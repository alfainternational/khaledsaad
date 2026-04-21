<?php

namespace App\Domain\WorkspaceData\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceData extends Model
{
    use SoftDeletes;

    protected $table = 'workspace_data';

    protected $fillable = [
        'workspace_id',
        'project_id',
        'key',
        'value_json',
    ];

    protected $casts = [
        'value_json' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
