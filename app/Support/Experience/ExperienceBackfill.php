<?php

namespace App\Support\Experience;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\User;

class ExperienceBackfill
{
    public function run(): int
    {
        $affected = 0;

        User::query()
            ->whereNull('experience_selected_at')
            ->with('workspaces.projects')
            ->eachById(function (User $user) use (&$affected): void {
                $hasProjects = $user->workspaces->contains(
                    fn ($workspace): bool => $workspace->projects->isNotEmpty(),
                );
                $hasLearning = $this->hasRealLearning($user);

                if (! $user->isAdmin() && ! $hasProjects && ! $hasLearning) {
                    return;
                }

                $now = now();
                $initial = $user->isAdmin() || $hasProjects
                    ? Experience::BUSINESS
                    : Experience::LEARNING;
                $values = [
                    'initial_experience' => $initial->value,
                    'active_experience' => $initial->value,
                    'experience_selected_at' => $now,
                ];

                if ($user->isAdmin() || $hasProjects) {
                    $values['business_experience_enabled_at'] = $now;
                }

                if ($user->isAdmin() || $hasLearning) {
                    $values['learning_experience_enabled_at'] = $now;
                }

                $user->forceFill($values)->save();
                $affected++;
            });

        return $affected;
    }

    private function hasRealLearning(User $user): bool
    {
        return MarketingLearningRun::query()
            ->where(function ($query) use ($user): void {
                $query->where('started_by', $user->id)
                    ->orWhere(function ($legacy) use ($user): void {
                        $legacy->whereNull('started_by')
                            ->whereHas('workspace', fn ($workspace) => $workspace->where('owner_id', $user->id));
                    });
            })
            ->with('attempts')
            ->get()
            ->contains(function (MarketingLearningRun $run): bool {
                if ($run->completed_exercises > 0) {
                    return true;
                }

                return $run->attempts->contains(function (MarketingExerciseAttempt $attempt): bool {
                    if ($attempt->status !== MarketingExerciseAttempt::STATUS_DRAFT) {
                        return true;
                    }

                    return collect($attempt->answers ?? [])->contains(
                        fn ($answer): bool => is_array($answer)
                            ? collect($answer)->filter(fn ($value) => filled($value))->isNotEmpty()
                            : filled($answer),
                    );
                });
            });
    }
}
