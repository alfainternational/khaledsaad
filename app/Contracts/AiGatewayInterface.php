<?php

namespace App\Contracts;

interface AiGatewayInterface
{
    /**
     * @return array<string, mixed>|null Raw API response
     */
    public function requestContent(string $prompt, ?string $systemPrompt = null): ?array;

    public function generateText(string $prompt, ?string $systemPrompt = null): ?string;
}
