<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;
use Illuminate\Support\Facades\Http;

class SearxngSearchGateway implements WebSearchGateway
{
    public function __construct(private readonly ?string $baseUrl) {}

    public function search(string $query, int $limit = 5): array
    {
        $baseUrl = rtrim(trim((string) $this->baseUrl), '/');
        if ($baseUrl === '' || trim($query) === '') {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(4)
                ->timeout(10)
                ->get($baseUrl.'/search', [
                    'q' => trim($query),
                    'format' => 'json',
                    'language' => 'ar',
                    'safesearch' => 1,
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('results', []))
                ->filter(fn ($item): bool => is_array($item))
                ->take(max(1, $limit))
                ->map(fn (array $item): array => [
                    'title' => (string) ($item['title'] ?? ''),
                    'url' => (string) ($item['url'] ?? ''),
                    'snippet' => (string) ($item['content'] ?? ''),
                ])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
