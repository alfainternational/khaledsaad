<?php

namespace App\Modules\Shared\I18n;

use Symfony\Component\Finder\Finder;

/**
 * ما يبقى عربيًّا على الشاشة رغم أن الكتالوج مكتمل ١٠٠٪.
 *
 * سبب وجود هذا الماسح أن `i18n:audit` كان يقيس **الكتالوج** ويُسمّيه
 * **التغطية**. والفرق بينهما هو كل العطل: النصّ الذي لا يدخل الكتالوج
 * أصلًا لا يظهر في عدّاد النقص، فيقول التدقيق «١٠٠٪ نظيف» عن واقع فيه
 * ٣٦ شاشة تعرض عربيًّا داخل واجهة إنجليزية. مقياسٌ لا يرى العطل أسوأ من
 * غياب المقياس، لأنه يوقف البحث عنه.
 *
 * الفئات الأربع أدناه ليست كل ما يمكن أن يتسرّب، لكنها ما تسرّب فعلًا —
 * كلٌّ منها عطلٌ وقع وأُصلح، وهذا الماسح يمنع عودته.
 */
final class ResidueScanner
{
    private const ARABIC = '/[\x{0600}-\x{06FF}]/u';

    public function __construct(private readonly BladeTranslator $translator = new BladeTranslator) {}

    /**
     * كل ما وجده الماسح، مجموعًا بفئته.
     *
     * @return array<string, array<int, array{file: string, line: int, text: string}>>
     */
    public function scan(): array
    {
        return array_filter([
            'attribute' => $this->attributeResidue(),
            'directive' => $this->directiveResidue(),
            'inline-script' => $this->inlineScriptResidue(),
            'javascript' => $this->javascriptResidue(),
        ], fn (array $found): bool => $found !== []);
    }

    /**
     * رسائل الإطار التي تُعرض كمفاتيح خام.
     *
     * تُفحص بالتنفيذ لا بقراءة الملفات: وجود `lang/ar/validation.php` لا
     * يعني أن `trans()` تصل إليه — فقد يُسمّى المجلد بغير رمز اللغة، أو
     * تُضاف لغة رابعة بلا ملفاتها. السؤال الوحيد الذي يهمّ هو ما يراه
     * المستخدم، وجوابه استدعاءٌ واحد.
     *
     * @param  array<int, string>  $locales
     * @return array<string, array<int, string>> اللغة ← المفاتيح المكسورة
     */
    public function frameworkLines(array $locales): array
    {
        $keys = [
            'validation.required', 'validation.email', 'validation.max.string',
            'validation.integer', 'validation.confirmed',
            'pagination.previous', 'pagination.next',
            'passwords.sent', 'passwords.token', 'passwords.throttled',
            'auth.failed', 'auth.throttle',
        ];

        $original = app()->getLocale();
        $broken = [];

        foreach ($locales as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key) {
                if (trans($key) === $key) {
                    $broken[$locale][] = $key;
                }
            }
        }

        app()->setLocale($original);

        return $broken;
    }

    /**
     * سمات معروضة قيمتها عربية بلا تغليف.
     *
     * `data-label` كانت أوضح مثال: مستثناة كـ«عقد برمجي»، بينما
     * `workspace.css` يعرضها بـ`content: attr(…)` — أي أنها عناوين أعمدة
     * الجداول على الجوال. القاعدة تنظر إلى شكل السمة لا إلى من يقرأها.
     *
     * الفحص على **ناتج المغلّف** لا على المصدر: السمة العادية تُغلَّف عند
     * التصريف وتبقى في المصدر عربية خامًّا — وقراءة المصدر تُبلّغ عن ١٦١
     * تسربًا وهميًّا. التسرّب الحقيقي هو ما يبقى بعد مرور المغلّف: قيمة
     * تخلط النصّ بـ`{{ }}`، أو سمة داخل كتلة مبهمة كـ`<textarea>`.
     *
     * @return array<int, array{file: string, line: int, text: string}>
     */
    private function attributeResidue(): array
    {
        $attributes = (array) config('locales.scan.blade.attributes', []);

        if ($attributes === []) {
            return [];
        }

        $names = implode('|', array_map('preg_quote', $attributes));
        $found = [];

        foreach ($this->blades() as $relative => $blade) {
            if (preg_match_all(
                '/(?<=\s)('.$names.')\s*=\s*"([^"]*)"/iu',
                $blade['rewritten'],
                $matches,
                PREG_SET_ORDER,
            ) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                if (preg_match(self::ARABIC, $match[2]) === 0) {
                    continue;
                }

                // مرّت بالترجمة: القيمة كلها — أو ما فيها من نصّ — نداءُ `__()`.
                if (str_contains($match[2], '__(')) {
                    continue;
                }

                $found[] = $this->hit(
                    $relative,
                    $this->lineOf($blade['lines'], $match[0]),
                    $match[1].'="'.$match[2].'"',
                );
            }
        }

        return $found;
    }

    /**
     * تسميات داخل مصفوفات `@foreach` و`@include`.
     *
     * المغلّف الآلي يتخطّى التوجيهات المركّبة لأن محتواها تعبير PHP كامل،
     * فبقيت تسميات المرشّحات والحالات («مسودة/منشور/مؤرشف») عربية.
     *
     * @return array<int, array{file: string, line: int, text: string}>
     */
    private function directiveResidue(): array
    {
        $found = [];

        foreach ($this->blades() as $relative => $blade) {
            $open = false;
            $depth = 0;

            foreach ($blade['lines'] as $number => $line) {
                if (! $open && preg_match('/@(foreach|include|includeIf|includeWhen|each)\b/u', $line) === 1) {
                    $open = true;
                    $depth = 0;
                }

                if ($open) {
                    if (preg_match_all("/=>\s*'([^']*)'/u", $line, $matches, PREG_SET_ORDER) > 0) {
                        foreach ($matches as $match) {
                            if (preg_match(self::ARABIC, $match[1]) === 1) {
                                $found[] = $this->hit($relative, $number, $match[0]);
                            }
                        }
                    }

                    $depth += substr_count($line, '(') - substr_count($line, ')');

                    if ($depth <= 0) {
                        $open = false;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * سلاسل عربية داخل `<script>` في القوالب.
     *
     * `<script>` كتلة مبهمة عند المغلّف — المسافات والصياغة فيها ذات معنى
     * فلا تُلمس. والمخرج هو `@js(__('…'))` مكتوبةً بيد كاتب القالب:
     * `{{ }}` وحدها تطبع النصّ بلا علامتَي اقتباس فتكسر الشيفرة.
     *
     * @return array<int, array{file: string, line: int, text: string}>
     */
    private function inlineScriptResidue(): array
    {
        $found = [];

        foreach ($this->blades() as $relative => $blade) {
            $source = implode("\n", $blade['lines']);

            if (preg_match_all('#<script\b([^>]*)>(.*?)</script>#su', $source, $blocks, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($blocks as $block) {
                // بيانات منظّمة يقرأها محرك بحث، لا شيفرة تُعرض.
                if (str_contains($block[1], 'application/ld+json')) {
                    continue;
                }

                foreach (explode("\n", $block[2]) as $line) {
                    $trimmed = ltrim($line);

                    if ($trimmed === '' || preg_match('#^(//|\*|/\*)#', $trimmed) === 1) {
                        continue;
                    }

                    if (preg_match_all('/([\'"])((?:(?!\1)[^\\\\\n]|\\\\.)*)\1/u', $line, $matches, PREG_SET_ORDER) === 0) {
                        continue;
                    }

                    foreach ($matches as $match) {
                        if (preg_match(self::ARABIC, $match[2]) === 0) {
                            continue;
                        }

                        // داخل نداء ترجمة على السطر نفسه — مغلَّفة.
                        if (str_contains($line, '__(') || str_contains($line, '@lang(')) {
                            continue;
                        }

                        $found[] = $this->hit($relative, $this->lineOf($blade['lines'], $line), $match[0]);
                    }
                }
            }
        }

        return $found;
    }

    /**
     * سلاسل عربية في حزمة JavaScript خارج `t()`.
     *
     * @return array<int, array{file: string, line: int, text: string}>
     */
    private function javascriptResidue(): array
    {
        $found = [];

        foreach ((array) config('locales.scan.js.roots', []) as $root) {
            $directory = base_path($root);

            if (! is_dir($directory)) {
                continue;
            }

            foreach (Finder::create()->files()->in($directory)->name('*.js') as $file) {
                $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $file->getPathname()));

                // ملف المساعدة نفسه يشرح آليته بالعربية في تعليقاته.
                if (str_ends_with($relative, '/i18n.js')) {
                    continue;
                }

                foreach (preg_split('/\r?\n/', (string) $file->getContents()) ?: [] as $index => $line) {
                    $trimmed = ltrim($line);

                    if ($trimmed === '' || preg_match('#^(//|\*|/\*)#', $trimmed) === 1) {
                        continue;
                    }

                    if (preg_match_all('/([\'"`])((?:(?!\1)[^\\\\]|\\\\.)*)\1/u', $line, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
                        continue;
                    }

                    foreach ($matches as $match) {
                        if (preg_match(self::ARABIC, $match[2][0]) === 0) {
                            continue;
                        }

                        // مسبوقة بـ`t(` مباشرةً — مغلَّفة.
                        if (preg_match('/\bt\($/u', substr($line, max(0, $match[0][1] - 3), 3)) === 1) {
                            continue;
                        }

                        // قالب نصّي يحمل `t(` بداخله: الترجمة داخله لا حوله.
                        if ($match[1][0] === '`' && str_contains($match[2][0], 't(')) {
                            continue;
                        }

                        $found[] = $this->hit($relative, $index + 1, trim($line));
                    }
                }
            }
        }

        return $found;
    }

    /**
     * كل قالب مرّة واحدة: أسطره كما كُتبت، وناتج المغلّف عليه.
     *
     * @return array<string, array{lines: array<int, string>, rewritten: string}>
     */
    private function blades(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $exclude = array_map(
            fn (string $path): string => str_replace('\\', '/', base_path($path)),
            (array) config('locales.scan.blade.exclude', []),
        );

        $cache = [];

        foreach ((array) config('locales.scan.blade.roots', ['resources/views']) as $root) {
            $directory = base_path($root);

            if (! is_dir($directory)) {
                continue;
            }

            foreach (Finder::create()->files()->in($directory)->name('*.blade.php') as $file) {
                $absolute = str_replace('\\', '/', $file->getPathname());

                foreach ($exclude as $excluded) {
                    if (str_starts_with($absolute, $excluded)) {
                        continue 2;
                    }
                }

                $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', $absolute);
                $source = (string) $file->getContents();
                $lines = preg_split('/\r?\n/', $source) ?: [];

                $cache[$relative] = [
                    'lines' => array_combine(range(1, max(1, count($lines))), $lines ?: ['']),
                    'rewritten' => $this->translator->rewrite($source, $relative),
                ];
            }
        }

        return $cache;
    }

    /**
     * رقم السطر في المصدر — تقريبًا بالاحتواء، لأن المطابقة قد تأتي من
     * ناتج المغلّف حيث تُدمج الأسطر أحيانًا.
     *
     * @param  array<int, string>  $lines
     */
    private function lineOf(array $lines, string $needle): int
    {
        $needle = trim($needle);

        foreach ($lines as $number => $line) {
            if ($line === $needle || ($needle !== '' && str_contains($line, $needle))) {
                return $number;
            }
        }

        return 0;
    }

    /**
     * @return array{file: string, line: int, text: string}
     */
    private function hit(string $file, int $line, string $text): array
    {
        return [
            'file' => $file,
            'line' => $line,
            'text' => mb_strlen($text) > 110 ? mb_substr($text, 0, 107).'…' : $text,
        ];
    }
}
