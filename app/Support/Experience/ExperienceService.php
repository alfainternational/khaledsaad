<?php

namespace App\Support\Experience;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExperienceService
{
    public function selectInitial(User $user, Experience $experience): User
    {
        return DB::transaction(function () use ($user, $experience): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->initial_experience !== null) {
                throw new DomainException('Initial experience is immutable.');
            }

            $now = now();
            $locked->forceFill([
                'initial_experience' => $experience,
                'active_experience' => $experience,
                $experience->enabledAtColumn() => $now,
                'experience_selected_at' => $now,
            ])->save();

            Log::info('experience_selected', [
                'user_id' => $locked->id,
                'experience' => $experience->value,
            ]);

            return $locked->refresh();
        });
    }

    public function activate(User $user, Experience $experience): User
    {
        return DB::transaction(function () use ($user, $experience): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $event = 'experience_activated';

            if ($locked->initial_experience === null) {
                $now = now();
                $locked->forceFill([
                    'initial_experience' => $experience,
                    'active_experience' => $experience,
                    $experience->enabledAtColumn() => $now,
                    'experience_selected_at' => $now,
                ])->save();
                $event = 'experience_selected';
            } elseif ($locked->{$experience->enabledAtColumn()} === null) {
                $locked->forceFill([$experience->enabledAtColumn() => now()])->save();
            } else {
                return $locked->refresh();
            }

            Log::info($event, [
                'user_id' => $locked->id,
                'experience' => $experience->value,
            ]);

            return $locked->refresh();
        });
    }

    public function switch(User $user, Experience $experience): User
    {
        return DB::transaction(function () use ($user, $experience): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $this->isEnabled($locked, $experience)) {
                throw new ExperienceNotEnabled($experience);
            }

            if ($locked->active_experience !== $experience) {
                $locked->forceFill(['active_experience' => $experience])->save();

                Log::info('experience_switched', [
                    'user_id' => $locked->id,
                    'experience' => $experience->value,
                ]);
            }

            return $locked->refresh();
        });
    }

    /** @return list<string> */
    public function enabled(User $user): array
    {
        return array_values(array_map(
            fn (Experience $experience): string => $experience->value,
            array_filter(Experience::cases(), fn (Experience $experience): bool => $this->isEnabled($user, $experience)),
        ));
    }

    private function isEnabled(User $user, Experience $experience): bool
    {
        return $user->isAdmin() || $user->{$experience->enabledAtColumn()} !== null;
    }
}
