<?php

namespace App\Support\Content;

use App\Models\Content;

class ContentStructuredData
{
    public function personJson(): string
    {
        return $this->encode([
            '@context' => 'https://schema.org',
            '@graph' => [$this->personNode()],
        ]);
    }

    /** @param array<string, mixed> $learning */
    public function forContent(Content $content, array $learning, bool $includeProtectedDetails = true): string
    {
        $url = route('content.show', $content);
        $description = $content->seo_description ?: $content->excerpt;
        $imagePath = $content->learning_meta['cover']['og'] ?? $content->cover_image_path;
        $image = $this->absoluteUrl($imagePath);
        $graph = [
            $this->personNode(),
            [
                '@type' => 'Article',
                '@id' => $url.'#article',
                'headline' => $content->title,
                'description' => $description,
                'url' => $url,
                'mainEntityOfPage' => $url,
                'inLanguage' => 'ar',
                'image' => $image ? [
                    '@type' => 'ImageObject',
                    'url' => $image,
                    'width' => 1200,
                    'height' => 630,
                ] : null,
                'datePublished' => $content->published_at?->toAtomString(),
                'dateModified' => $content->updated_at?->toAtomString(),
                'author' => ['@id' => url('/').'#person'],
                'publisher' => ['@id' => url('/').'#person'],
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => $url.'#breadcrumbs',
                'itemListElement' => $this->breadcrumbs($content),
            ],
        ];

        if ($learning['enabled'] ?? false) {
            $seriesId = route('content.index', ['category' => $content->category?->slug]).'#series';
            $graph[] = [
                '@type' => 'CreativeWorkSeries',
                '@id' => $seriesId,
                'name' => $content->learning_meta['series'] ?? 'تعلم التسويق',
                'url' => route('content.index', ['category' => $content->category?->slug]),
                'inLanguage' => 'ar',
                'numberOfItems' => $learning['total'],
            ];
            $graph[] = [
                '@type' => 'LearningResource',
                '@id' => $url.'#lesson',
                'name' => $content->title,
                'description' => $description,
                'url' => $url,
                'inLanguage' => 'ar',
                'learningResourceType' => 'درس تطبيقي',
                'educationalLevel' => 'مبتدئ إلى متوسط',
                'timeRequired' => $content->duration_minutes ? 'PT'.$content->duration_minutes.'M' : null,
                'position' => $content->learning_order,
                'isPartOf' => ['@id' => $seriesId],
                'author' => ['@id' => url('/').'#person'],
            ];

            $faq = $includeProtectedDetails
                ? collect($content->learning_meta['faq'] ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item)
                        && filled($item['question'] ?? null)
                        && is_string($item['question'])
                        && filled($item['answer'] ?? null)
                        && is_string($item['answer']))
                    ->take(5)
                : collect();
            if ($faq->isNotEmpty()) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    '@id' => $url.'#faq',
                    'mainEntity' => $faq->map(fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ])->values()->all(),
                ];
            }
        }

        return $this->encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ]);
    }

    /** @return array<string, mixed> */
    private function personNode(): array
    {
        return [
            '@type' => 'Person',
            '@id' => url('/').'#person',
            'name' => config('brand.name'),
            'alternateName' => config('brand.name_en'),
            'url' => url('/'),
            'jobTitle' => config('brand.professional_headline'),
            'hasOccupation' => [
                '@type' => 'Occupation',
                'name' => config('brand.professional_headline'),
                'occupationLocation' => [
                    '@type' => 'City',
                    'name' => 'عرعر، المملكة العربية السعودية',
                ],
            ],
            'description' => config('brand.about.0'),
            'telephone' => config('brand.contact.phone'),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'عرعر',
                'addressRegion' => 'الحدود الشمالية',
                'addressCountry' => 'SA',
            ],
            'sameAs' => array_values(array_filter([
                config('brand.contact.linkedin'),
                config('brand.contact.x'),
            ])),
            'knowsAbout' => config('brand.skills'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function breadcrumbs(Content $content): array
    {
        $items = [
            ['name' => 'الرئيسية', 'item' => route('home')],
            ['name' => 'المكتبة', 'item' => route('content.index')],
        ];

        if ($content->category) {
            $items[] = [
                'name' => $content->category->name,
                'item' => route('content.index', ['category' => $content->category->slug]),
            ];
        }

        $items[] = ['name' => $content->title, 'item' => route('content.show', $content)];

        return collect($items)->values()->map(fn (array $item, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $item['name'],
            'item' => $item['item'],
        ])->all();
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return str_starts_with((string) $path, 'http') ? (string) $path : url((string) $path);
    }

    /** @param array<string, mixed> $data */
    private function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRETTY_PRINT
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR,
        );
    }
}
