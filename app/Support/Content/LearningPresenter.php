<?php

namespace App\Support\Content;

use App\Models\Content;
use App\Modules\Learning\MarketingCourseCatalog;
use Illuminate\Support\Str;

class LearningPresenter
{
    public function __construct(private readonly MarketingCourseCatalog $catalog) {}

    /** @return array<string, mixed> */
    public function present(Content $content): array
    {
        if ($content->learning_order === null) {
            return ['enabled' => false, 'applications' => []];
        }

        $series = Content::query()
            ->published()
            ->whereNotNull('learning_order')
            ->when(
                Str::startsWith((string) $content->source_key, 'marketing-course-'),
                fn ($query) => $query->where('source_key', 'like', 'marketing-course-%'),
                fn ($query) => $query
                    ->when(
                        filled($content->learning_meta['series'] ?? null),
                        fn ($seriesQuery) => $seriesQuery->where('learning_meta->series', $content->learning_meta['series']),
                        fn ($seriesQuery) => $seriesQuery->when($content->category_id, fn ($categoryQuery) => $categoryQuery->where('category_id', $content->category_id)),
                    ),
            );

        $previous = (clone $series)
            ->where('learning_order', '<', $content->learning_order)
            ->orderByDesc('learning_order')
            ->first();
        $next = (clone $series)
            ->where('learning_order', '>', $content->learning_order)
            ->orderBy('learning_order')
            ->first();
        $outline = collect($content->learning_meta['outline'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['id'] ?? null) && filled($item['title'] ?? null))
            ->values()
            ->all();

        return [
            'enabled' => true,
            'order' => $content->learning_order,
            'total' => (clone $series)->count(),
            'outline' => $outline,
            'previous' => $previous,
            'next' => $next,
            'progress_key' => 'learning-progress-'.($content->source_key ?: $content->slug),
            'cover_alt' => $content->learning_meta['cover']['alt'] ?? $content->title,
            'applications' => Str::startsWith((string) $content->source_key, 'marketing-course-')
                ? (collect($this->catalog->lessons())->firstWhere('number', $content->learning_order)['exercises'] ?? [])
                : [],
        ];
    }
}
