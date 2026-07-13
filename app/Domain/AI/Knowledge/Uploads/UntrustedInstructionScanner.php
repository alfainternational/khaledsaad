<?php

namespace App\Domain\AI\Knowledge\Uploads;

class UntrustedInstructionScanner
{
    private const PATTERNS = [
        'ignore_previous_instructions' => '/ignore\s+(all\s+)?previous\s+instructions|disregard\s+(all\s+)?previous|تجاهل\s+(كل\s+)?التعليمات\s+السابقة/iu',
        'system_prompt_extraction' => '/system\s+prompt|reveal\s+(your\s+)?instructions|تعليمات\s+النظام|اكشف\s+تعليمات/iu',
        'role_override' => '/you\s+are\s+now|act\s+as\s+(an?|the)|أنت\s+الآن|تصر[ّ]?ف\s+ك/iu',
        'command_execution' => '/execute\s+(this\s+)?command|run\s+(this\s+)?command|نف[ّ]?ذ\s+(هذا\s+)?الأمر/iu',
    ];

    /** @return list<string> */
    public function scan(string $content): array
    {
        $flags = [];
        foreach (self::PATTERNS as $flag => $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }
}
