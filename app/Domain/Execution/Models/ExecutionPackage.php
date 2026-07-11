<?php

namespace App\Domain\Execution\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExecutionPackage extends Model
{
    use HasPublicId;
    use SoftDeletes;

    public const STATUSES = ['proposed', 'in_review', 'approved', 'in_progress', 'executed', 'measuring'];

    protected $fillable = [
        'public_id',
        'workspace_id',
        'project_id',
        'recommendation_id',
        'title',
        'problem',
        'evidence',
        'decision',
        'measurement_plan',
        'owner_user_id',
        'status',
        'deadline',
        'created_by',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ExecutionTask::class)->orderBy('order_index');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ExecutionAsset::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ExecutionReport::class)->latest('id');
    }
}
