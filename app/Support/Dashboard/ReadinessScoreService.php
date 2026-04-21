<?php

namespace App\Support\Dashboard;

class ReadinessScoreService
{
    /**
     * @param  array<int, string>  $completedToolCodes
     * @return array<string, array<string, int|string>>
     */
    public function calculate(array $completedToolCodes): array
    {
        $lookup = array_flip($completedToolCodes);

        return collect(ReadinessCatalog::all())
            ->mapWithKeys(function (array $dimension, string $key) use ($lookup): array {
                $total = count($dimension['tools']);
                $completed = collect($dimension['tools'])
                    ->filter(fn (string $toolCode): bool => isset($lookup[$toolCode]))
                    ->count();

                $score = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

                return [
                    $key => [
                        'label' => $dimension['label'],
                        'score' => $score,
                        'completed' => $completed,
                        'total' => $total,
                    ],
                ];
            })
            ->all();
    }
}
