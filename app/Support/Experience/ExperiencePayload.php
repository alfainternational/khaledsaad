<?php

namespace App\Support\Experience;

use App\Models\User;

class ExperiencePayload
{
    public function __construct(private readonly ExperienceService $experiences) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $enabled = $this->experiences->enabled($user);

        return [
            'initial_experience' => $user->initial_experience?->value,
            'active_experience' => $user->active_experience?->value,
            'enabled_experiences' => $enabled,
            'capabilities' => [
                'can_activate_business' => ! in_array(Experience::BUSINESS->value, $enabled, true),
                'can_activate_learning' => ! in_array(Experience::LEARNING->value, $enabled, true),
                'can_switch_experience' => count($enabled) > 1,
            ],
            'actions' => [
                'activate_experience' => '/api/v1/experiences/{experience}/activate',
                'switch_experience' => '/api/v1/experiences/{experience}/switch',
            ],
        ];
    }
}
