<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingLearningRun extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'workspace_id', 'project_id', 'started_by', 'status', 'current_exercise_key',
        'completed_exercises', 'average_score', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function startFor(Project $project, ?User $user = null): self
    {
        return self::startForWorkspace($project->workspace, $user, $project);
    }

    public static function startForWorkspace(Workspace $workspace, ?User $user = null, ?Project $project = null): self
    {
        if ($project !== null && $project->workspace_id !== $workspace->id) {
            throw new \InvalidArgumentException('Project does not belong to the learning workspace.');
        }

        $run = self::query()
            ->when($project, fn ($query) => $query->where('project_id', $project->id))
            ->where('workspace_id', $workspace->id)
            ->where('started_by', $user?->id)
            ->oldest('id')
            ->first();

        if ($run === null && $project !== null) {
            $run = self::query()->where('project_id', $project->id)->first();
        }

        if ($run !== null) {
            if ($run->workspace_id === null) {
                $run->forceFill(['workspace_id' => $workspace->id])->save();
            }

            return $run;
        }

        return self::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project?->id,
            'started_by' => $user?->id,
            'status' => self::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MarketingExerciseAttempt::class);
    }

    public function attemptFor(string $exerciseKey): MarketingExerciseAttempt
    {
        return $this->attempts()->firstOrCreate(
            ['exercise_key' => $exerciseKey],
            ['answers' => [], 'status' => MarketingExerciseAttempt::STATUS_DRAFT],
        );
    }

    public function refreshProgress(int $exerciseCount): self
    {
        $completed = $this->attempts()->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)->count();
        $average = $this->attempts()->whereNotNull('final_score')->avg('final_score');

        $this->forceFill([
            'completed_exercises' => $completed,
            'average_score' => $average === null ? null : (int) round((float) $average),
            'status' => $completed >= $exerciseCount ? self::STATUS_COMPLETED : self::STATUS_ACTIVE,
            'completed_at' => $completed >= $exerciseCount ? ($this->completed_at ?? now()) : null,
        ])->save();

        return $this->refresh();
    }
}
