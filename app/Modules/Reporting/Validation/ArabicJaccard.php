<?php

namespace App\Modules\Reporting\Validation;

use App\Modules\Shared\Text\ArabicText;

final class ArabicJaccard
{
    private const STOP_WORDS = ['في', 'من', 'الى', 'إلى', 'على', 'عن', 'ثم', 'او', 'أو', 'هو', 'هي', 'هذا', 'هذه', 'التي', 'الذي', 'مع'];

    public function similarity(string|array $left, string|array $right): float
    {
        $a = $this->tokens($left);
        $b = $this->tokens($right);

        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = array_intersect($a, $b);
        $union = array_unique([...$a, ...$b]);

        return count($intersection) / count($union);
    }

    /** @return array<int, string> */
    private function tokens(string|array $value): array
    {
        $text = ArabicText::normalize(is_array($value) ? implode(' ', array_map('strval', $value)) : $value);
        $text = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $word): bool => mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true))));
    }
}
