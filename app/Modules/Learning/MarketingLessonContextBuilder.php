<?php

namespace App\Modules\Learning;

use App\Models\Content;
use App\Models\MarketingExerciseAttempt;
use App\Modules\Brain\BrainReader;

class MarketingLessonContextBuilder
{
    public function __construct(
        private readonly MarketingCourseCatalog $catalog,
        private readonly BrainReader $brain,
    ) {}

    /** @return array<string, mixed> */
    public function build(MarketingExerciseAttempt $attempt, string $questionKey, ?string $sectionId = null): array
    {
        $exercise = $this->catalog->exercise($attempt->exercise_key);
        $lessonDefinition = $this->catalog->lessonFor($attempt->exercise_key);
        $question = $this->catalog->question($attempt->exercise_key, $questionKey);
        $lesson = Content::query()
            ->published()
            ->where('source_key', sprintf('marketing-course-%02d', $lessonDefinition['number']))
            ->first();
        $attempt->loadMissing('run.project');
        $project = $attempt->run->project;
        $body = (string) ($lesson?->body_html ?? '');

        return [
            'catalog_version' => $this->catalog->version(),
            'lesson' => [
                'number' => $lessonDefinition['number'],
                'title' => $lesson?->title ?? $lessonDefinition['title'],
                'outline' => $lesson?->learning_meta['outline'] ?? [],
                'full_text' => $this->plainText($body),
                'content_updated_at' => $lesson?->updated_at?->toIso8601String(),
            ],
            'exercise' => [
                'key' => $exercise['key'],
                'title' => $exercise['title'],
                'purpose' => $exercise['purpose'],
                'deliverable' => $exercise['deliverable'],
            ],
            'question' => [
                'key' => $question['key'],
                'label' => $question['label'],
                'rubric' => $question['rubric'],
                'help' => $question['help'],
                'example' => $question['example'],
            ],
            'active_section' => $this->section($body, $lesson?->learning_meta['outline'] ?? [], $sectionId),
            'related_lessons' => $this->adjacentLessons($lessonDefinition['number']),
            'answers' => [
                'current' => $attempt->answers[$questionKey] ?? null,
                'exercise' => $attempt->answers ?? [],
                'previous_completed' => $this->previousAnswers($attempt),
            ],
            'project' => $project === null ? null : [
                'name' => $project->name,
                'sector' => $project->sector,
                'industry' => $project->industry,
                'known_facts' => $this->brain->facts($project)->map(fn ($fact) => [
                    'key' => $fact->key,
                    'value' => $fact->value_json['value'] ?? null,
                    'evidence_level' => $fact->evidence_level->value,
                ])->values()->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function section(string $body, array $outline, ?string $sectionId): array
    {
        if (! filled($sectionId)) {
            return ['id' => null, 'title' => null, 'text' => null];
        }

        $title = collect($outline)->firstWhere('id', $sectionId)['title'] ?? null;
        $quoted = preg_quote((string) $sectionId, '~');
        $text = null;

        if (preg_match('~<section[^>]+id=["\']'.$quoted.'["\'][^>]*>(.*?)</section>~isu', $body, $match) === 1) {
            $text = $this->plainText($match[1]);
        }

        return ['id' => $sectionId, 'title' => $title, 'text' => $text];
    }

    /** @return array{previous: array<string, mixed>|null, next: array<string, mixed>|null} */
    private function adjacentLessons(int $order): array
    {
        $query = Content::query()->published()->where('source_key', 'like', 'marketing-course-%');
        $previous = (clone $query)->where('learning_order', '<', $order)->orderByDesc('learning_order')->first();
        $next = (clone $query)->where('learning_order', '>', $order)->orderBy('learning_order')->first();
        $map = fn (?Content $content): ?array => $content === null ? null : [
            'order' => $content->learning_order,
            'title' => $content->title,
            'summary' => $content->excerpt,
        ];

        return ['previous' => $map($previous), 'next' => $map($next)];
    }

    /** @return array<int, array<string, mixed>> */
    private function previousAnswers(MarketingExerciseAttempt $attempt): array
    {
        return $attempt->run->attempts()
            ->where('id', '!=', $attempt->id)
            ->where('status', MarketingExerciseAttempt::STATUS_COMPLETED)
            ->latest('evaluated_at')
            ->limit(5)
            ->get(['exercise_key', 'answers'])
            ->map(fn (MarketingExerciseAttempt $item) => [
                'exercise_key' => $item->exercise_key,
                'answers' => $item->answers,
            ])->all();
    }

    private function plainText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
