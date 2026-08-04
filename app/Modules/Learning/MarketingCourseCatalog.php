<?php

namespace App\Modules\Learning;

use InvalidArgumentException;

class MarketingCourseCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lessons(): array
    {
        return $this->load()['lessons'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exercises(): array
    {
        return collect($this->lessons())
            ->flatMap(fn (array $lesson) => collect($lesson['exercises'])->map(
                fn (array $exercise) => [
                    ...$exercise,
                    'lesson_number' => $lesson['number'],
                    'lesson_title' => $lesson['title'],
                    'source_url' => $lesson['source_url'],
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function exercise(string $key): array
    {
        return collect($this->exercises())->firstWhere('key', $key)
            ?? throw new InvalidArgumentException("Unknown marketing exercise: {$key}");
    }

    /**
     * @return array<string, mixed>
     */
    public function lessonFor(string $exerciseKey): array
    {
        return collect($this->lessons())->first(
            fn (array $lesson) => collect($lesson['exercises'])->contains('key', $exerciseKey),
        ) ?? throw new InvalidArgumentException("Unknown marketing exercise: {$exerciseKey}");
    }

    /**
     * @return array<string, mixed>
     */
    public function question(string $exerciseKey, string $questionKey): array
    {
        $exercise = $this->exercise($exerciseKey);

        return collect($exercise['questions'])->firstWhere('key', $questionKey)
            ?? throw new InvalidArgumentException("Unknown question {$questionKey} in {$exerciseKey}");
    }

    public function version(): int
    {
        return (int) $this->load()['version'];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        return $this->catalog ??= require database_path('data/learning/marketing-course.php');
    }
}
