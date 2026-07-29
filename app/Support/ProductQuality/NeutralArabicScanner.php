<?php

namespace App\Support\ProductQuality;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NeutralArabicScanner
{
    /**
     * @var array<string, string>
     */
    public const array REPLACEMENTS = [
        'دي' => 'هذه',
        'دا' => 'هذا',
        'وين' => 'أين',
        'شنو' => 'ما',
        'منو' => 'من',
        'إيش' => 'ما',
        'وش' => 'ما',
        'سوّي' => 'نفّذ',
        'سوي' => 'نفّذ',
        'كده' => 'هكذا',
        'دلوقتي' => 'الآن',
        'مش' => 'ليس',
        'عشان' => 'لكي',
        'اللي' => 'الذي',
        'شوف' => 'اطّلع',
        'تقدر' => 'يمكنك',
        'لحد' => 'حتى',
        'ما عندك' => 'ليس لديك',
        'يجيك' => 'يصل إليك',
        'حالك' => 'حالتك',
        'نرجّع' => 'نقدّم',
        'شفت' => 'رأيت',
        'ليه' => 'لماذا',
        'راح' => 'ذهب',
        'تاني' => 'آخر',
        'جات' => 'جاءت',
        'ما حد' => 'لا أحد',
        'بيتحول' => 'يتحول',
        'حأقدر' => 'هل أستطيع',
        'أوري' => 'أعرض',
        'تديه' => 'تقدمه',
        'بعدين' => 'لاحقًا',
        'لازم' => 'يلزم',
        'بتروح' => 'تذهب',
        'طالعة' => 'ناتجة',
        'نشتغل' => 'نعمل',
        'شغله' => 'عمله',
        'حأعيد' => 'هل سأعيد',
        'خلّي' => 'اجعل',
        'خلي' => 'اجعل',
        'خلّيه' => 'فعّل',
        'خليه' => 'فعّل',
        'ما في' => 'لا يوجد',
    ];

    /**
     * @param  list<string>  $paths
     * @return list<array{file: string, line: int, term: string, replacement: string, text: string}>
     */
    public function scan(array $paths): array
    {
        $issues = [];

        foreach ($this->files($paths) as $file) {
            if (realpath($file) === __FILE__ || $this->isQuotedSpeech($file)) {
                continue;
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if ($this->isCommentOnly($line)) {
                    continue;
                }

                foreach (self::REPLACEMENTS as $term => $replacement) {
                    if (! $this->containsTerm($line, $term)) {
                        continue;
                    }

                    $issues[] = [
                        'file' => $file,
                        'line' => $index + 1,
                        'term' => $term,
                        'replacement' => $replacement,
                        'text' => trim($line),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * ملفات تنقل كلام غيرنا لا خطابنا.
     *
     * المعيار يحكم **ما تقوله المنصة للمستخدم**: لهجة بيضاء بلمسة خليجية، لا
     * عامية ثقيلة. لكن بنك الأسئلة ليس خطابًا — هو محاكاة لما يكتبه مشترٍ
     * حقيقي في مربّع البحث، وCLAUDE.md §١٥ يوجب كتابته «بلسان مشترٍ حقيقي»
     * ويحذّر من الترجمة.
     *
     * إخضاعه للمعيار يفسد القياس نفسه: «ما أفضل مزوّد» سؤال لا يكتبه أحد،
     * والنموذج يجيب عليه بجواب أكاديمي بلا أسماء — فيخرج معدّل الذكر صفرًا
     * لعلامة قد تكون ظاهرة تمامًا في السؤال الحقيقي.
     *
     * الاستثناء بالمسار لا بتعليق داخلي: تعليق `@neutral-arabic-ignore` كان
     * سيصير بابًا يُفتح في أي ملف يزعج كاتبَه المدقّقُ.
     */
    private function isQuotedSpeech(string $file): bool
    {
        return str_ends_with(
            str_replace('\\', '/', (string) realpath($file)),
            'app/Modules/AiReadiness/QuestionBank.php',
        );
    }

    /**
     * @return list<array{file: string, line: int, term: string, replacement: string, text: string}>
     */
    public function scanDefaultPaths(): array
    {
        return $this->scan($this->defaultPaths());
    }

    /**
     * @return list<string>
     */
    public function defaultPaths(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            $root.DIRECTORY_SEPARATOR.'app',
            $root.DIRECTORY_SEPARATOR.'config',
            $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'tools',
            $root.DIRECTORY_SEPARATOR.'mobile'.DIRECTORY_SEPARATOR.'lib',
            $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views',
        ];
    }

    private function containsTerm(string $line, string $term): bool
    {
        $quoted = preg_quote($term, '/');
        $pattern = '/(?<![\p{Arabic}\p{L}\p{M}])'.$quoted.'(?![\p{Arabic}\p{L}\p{M}])/u';

        return preg_match($pattern, $line) === 1;
    }

    private function isCommentOnly(string $line): bool
    {
        $trimmed = ltrim($line);

        foreach (['//', '#', '/*', '*', '*/', '{{--', '--}}', '<!--', '-->'] as $prefix) {
            if (str_starts_with($trimmed, $prefix)) {
                return true;
            }
        }

        return $trimmed === '';
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'dart'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
