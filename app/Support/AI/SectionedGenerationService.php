<?php

namespace App\Support\AI;

/**
 * Sectioned (fan-out) generation: instead of one giant slow call, generate each required
 * output-contract section concurrently from the SAME shared context (coherence), then stitch
 * deterministically (completeness). Returns null on partial failure so the caller falls back
 * to the sequential single-call path — it never degrades the working pipeline.
 */
class SectionedGenerationService
{
    public function __construct(
        private readonly ParallelTextGenerator $parallel,
    ) {}

    /**
     * @param  list<string>  $sectionTitles
     */
    public function generate(string $sharedContextPrompt, ?string $systemPrompt, array $sectionTitles): ?string
    {
        $titles = collect($sectionTitles)
            ->map(fn (mixed $title): string => is_string($title) ? trim($title) : '')
            ->filter(fn (string $title): bool => $title !== '')
            ->unique()
            ->values()
            ->all();

        if (count($titles) < 2) {
            return null;
        }

        $prompts = array_map(
            fn (string $title): string => implode("\n\n", [
                $sharedContextPrompt,
                '[مهمة هذا النداء — قسم واحد فقط]',
                'اكتب الآن **قسماً واحداً فقط** من الملف، عنوانه بالضبط: «'.$title.'».',
                'ابدأ الإخراج مباشرةً بالسطر: ## '.$title,
                'اجعله كاملاً وجاهزاً للنسخ (النصوص/الجداول/القوائم الفعلية حسب طبيعة القسم)، بنفس اللهجة والقرارات والسوق الواردة في السياق أعلاه.',
                'ممنوع: كتابة أي قسم آخر، أو مقدمة، أو خاتمة عامة، أو تكرار عنوان الملف، أو رموز تعبيرية.',
            ]),
            $titles,
        );

        $results = $this->parallel->generate($prompts, $systemPrompt);

        $blocks = [];
        $failures = 0;

        foreach ($titles as $index => $title) {
            $text = $results[$index] ?? null;

            if (! is_string($text) || trim($text) === '') {
                $failures++;

                continue;
            }

            $blocks[] = $this->ensureHeading(trim($text), $title);
        }

        // فشل نصف الأقسام أو أكثر ⇒ لا نعتمد المخرج المتوازي (تجنّب ملف ناقص).
        if ($blocks === [] || $failures >= (int) ceil(count($titles) / 2)) {
            return null;
        }

        return implode("\n\n", $blocks);
    }

    private function ensureHeading(string $text, string $title): string
    {
        if (! str_starts_with(ltrim($text), '#')) {
            return '## '.$title."\n\n".$text;
        }

        return $text;
    }
}
