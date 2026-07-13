<?php

namespace App\Domain\AI\Chat;

class ChatAnswerExtractor
{
    public function __construct(private readonly int $maxAnswerChars = 12000) {}

    /** @param array<string, mixed> $result */
    public function extract(array $result): string
    {
        foreach (['answer', 'response', 'text'] as $key) {
            $value = $result[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $answer = trim(str_replace(["\r\n", "\r"], "\n", $value));
            if ($answer === '' || str_contains($answer, "\0") || ! mb_check_encoding($answer, 'UTF-8')) {
                continue;
            }

            if (mb_strlen($answer) > max(500, $this->maxAnswerChars)) {
                throw new \InvalidArgumentException('The chat answer exceeds its configured limit.');
            }

            return $answer;
        }

        throw new \InvalidArgumentException('The local model result has no valid chat answer.');
    }
}
