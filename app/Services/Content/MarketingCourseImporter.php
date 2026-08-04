<?php

namespace App\Services\Content;

use App\Models\Content;
use App\Models\ContentCategory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketingCourseImporter
{
    public function __construct(private readonly ContentHtmlSanitizer $sanitizer) {}

    /** @return array{created: int, updated: int, total: int} */
    public function import(bool $publish = false, ?string $manifestPath = null): array
    {
        $manifestPath ??= database_path('data/content/marketing-course/manifest.php');

        if (! is_file($manifestPath)) {
            throw new RuntimeException('Marketing course package is missing.');
        }

        /** @var array<string, mixed> $manifest */
        $manifest = require $manifestPath;
        $lessons = $this->validatedLessons($manifest, $manifestPath);

        return DB::transaction(function () use ($manifest, $lessons, $publish): array {
            $category = ContentCategory::query()->updateOrCreate(
                ['slug' => $manifest['course']['slug']],
                [
                    'name' => $manifest['course']['title'],
                    'description' => $manifest['course']['description'],
                    'icon' => 'graduation-cap',
                    'color' => '#2575ff',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            );

            $created = 0;
            $updated = 0;

            foreach ($lessons as $lesson) {
                $content = Content::query()->where('source_key', $lesson['source_key'])->first();

                if ($content === null && $lesson['order'] === 1 && $lesson['slug'] === '1') {
                    $content = Content::query()->where('slug', '1')->first();
                } elseif ($content === null && Content::query()->where('slug', $lesson['slug'])->exists()) {
                    throw new RuntimeException(sprintf(
                        'Cannot import lesson %d: slug "%s" belongs to unrelated content.',
                        $lesson['order'],
                        $lesson['slug'],
                    ));
                }

                $isNew = $content === null;
                $content ??= new Content;

                $status = $publish ? Content::STATUS_PUBLISHED : ($content->status ?: Content::STATUS_DRAFT);
                $publishedAt = $status === Content::STATUS_PUBLISHED
                    ? ($content->published_at ?? now())
                    : $content->published_at;

                $content->fill([
                    'type' => Content::TYPE_ARTICLE,
                    'category_id' => $category->id,
                    'title' => $lesson['title'],
                    'slug' => $lesson['slug'],
                    'source_key' => $lesson['source_key'],
                    'source_filename' => $lesson['source_filename'],
                    'source_text_hash' => $lesson['source_text_hash'],
                    'learning_order' => $lesson['order'],
                    'learning_meta' => $lesson['learning_meta'],
                    'excerpt' => $lesson['excerpt'],
                    'body_html' => $this->sanitizer->sanitize($lesson['body_html']),
                    'body_json' => $lesson['body_json'],
                    'cover_image_path' => $lesson['cover_image_path'],
                    'duration_minutes' => $lesson['duration_minutes'],
                    'status' => $status,
                    'access_level' => Content::ACCESS_PUBLIC,
                    'published_at' => $publishedAt,
                    'seo_title' => $lesson['seo_title'],
                    'seo_description' => $lesson['seo_description'],
                    'sort_order' => $lesson['order'],
                ])->save();

                $isNew ? $created++ : $updated++;
            }

            cache()->forget('sitemap.xml');

            return ['created' => $created, 'updated' => $updated, 'total' => count($lessons)];
        }, 3);
    }

    /** @param array<string, mixed> $manifest
     * @return list<array<string, mixed>>
     */
    private function validatedLessons(array $manifest, string $manifestPath): array
    {
        $entries = $manifest['lessons'] ?? null;

        if (! is_array($manifest['course'] ?? null)
            || ! filled($manifest['course']['slug'] ?? null)
            || ! filled($manifest['course']['title'] ?? null)
            || ! is_array($entries)
            || count($entries) !== 20) {
            throw new RuntimeException('Marketing course package must contain exactly 20 lessons and valid course metadata.');
        }

        $lessons = [];

        foreach (array_values($entries) as $index => $entry) {
            $expectedOrder = $index + 1;

            if (! is_array($entry)
                || (int) ($entry['order'] ?? 0) !== $expectedOrder
                || ($entry['source_key'] ?? null) !== sprintf('marketing-course-%02d', $expectedOrder)
                || ! is_string($entry['path'] ?? null)) {
                throw new RuntimeException("Marketing course manifest is invalid at lesson {$expectedOrder}.");
            }

            $path = $this->lessonPath($entry['path'], $manifestPath);

            if (! is_file($path)) {
                throw new RuntimeException("Marketing course lesson file is missing at order {$expectedOrder}.");
            }

            $lesson = require $path;

            if (! is_array($lesson)
                || (int) ($lesson['order'] ?? 0) !== $expectedOrder
                || ($lesson['source_key'] ?? null) !== $entry['source_key']
                || ! is_string($lesson['source_text'] ?? null)
                || ! is_string($lesson['source_text_hash'] ?? null)
                || ! hash_equals($lesson['source_text_hash'], hash('sha256', $lesson['source_text']))
                || ! is_string($lesson['slug'] ?? null)
                || ! is_string($lesson['body_html'] ?? null)
                || ! is_array($lesson['learning_meta'] ?? null)) {
                throw new RuntimeException("Marketing course lesson {$expectedOrder} failed integrity validation.");
            }

            $lessons[] = $lesson;
        }

        return $lessons;
    }

    private function lessonPath(string $path, string $manifestPath): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        if (str_starts_with(str_replace('\\', '/', $path), 'data/')) {
            return database_path($path);
        }

        return dirname($manifestPath).DIRECTORY_SEPARATOR.$path;
    }
}
