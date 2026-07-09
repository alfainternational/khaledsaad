<?php

namespace App\Contracts;

interface WebSearchGateway
{
    /**
     * بحث حيّ في الإنترنت.
     *
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    public function search(string $query, int $limit = 5): array;
}
