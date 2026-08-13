<?php

namespace App\Modules\Reporting\Contracts;

final class RecommendationCandidate
{
    /**
     * @param  array<int, string>  $actionSteps
     * @param  array<string, mixed>|null  $template
     * @param  array<int, string>  $degradeReasons
     * @param  array<int, string>  $fallbackCoaching
     * @param  array<string, mixed>  $source
     */
    public function __construct(
        public readonly string $objectiveId,
        public readonly ?int $objectiveDatabaseId,
        public readonly string $metricObjectiveId,
        public readonly ?int $metricObjectiveDatabaseId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $deliverable,
        public readonly string $doneWhen,
        public readonly string $firstFiveMinutes,
        public readonly string $expectedFailure,
        public readonly int $durationDays,
        public readonly string $impact,
        public readonly string $effort,
        public readonly string $metricLabel,
        public readonly array $actionSteps,
        public readonly ?array $template,
        public readonly bool $degraded,
        public readonly array $degradeReasons,
        public readonly array $fallbackCoaching,
        public readonly array $source,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->source,
            'objective_id' => $this->objectiveId,
            'title' => $this->title,
            'description' => $this->description,
            'deliverable' => $this->deliverable,
            'done_when' => $this->doneWhen,
            'first_five_minutes' => $this->firstFiveMinutes,
            'expected_failure' => $this->expectedFailure,
            'duration_days' => $this->durationDays,
            'impact' => $this->impact,
            'effort' => $this->effort,
            'metric' => [
                'label' => $this->metricLabel,
                'objective_id' => $this->metricObjectiveId,
            ],
            'action_steps' => $this->actionSteps,
            'template' => $this->template,
            'degraded' => $this->degraded,
            'degrade_reasons' => $this->degradeReasons,
            'fallback_coaching' => $this->fallbackCoaching,
        ];
    }
}
