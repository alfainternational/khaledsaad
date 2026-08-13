<?php

namespace App\Console\Commands\I18n;

use App\Modules\Shared\I18n\BladeTranslator;
use App\Modules\Shared\I18n\TranslationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * استخراج كل نص عربي معروض من القوالب إلى كتالوج المصدر.
 *
 * يُشغَّل بعد أي تغيير في الواجهة، وقبل `i18n:translate`. مخرجه هو
 * العقد: ما ليس في الكتالوج لن يُترجَم، وما فيه بلا ترجمة يُبلَّغ عنه في
 * `i18n:audit` بدل أن يظهر للمستخدم عربيًّا داخل واجهة إنجليزية.
 */
final class ExtractTranslations extends Command
{
    protected $signature = 'i18n:extract
                            {--dry : اعرض ما سيُستخرَج دون كتابة الكتالوج}';

    protected $description = 'استخراج نصوص الواجهة العربية من قوالب Blade إلى كتالوج الترجمة';

    public function handle(BladeTranslator $translator, TranslationCatalog $catalog): int
    {
        $roots = (array) config('locales.scan.blade.roots', ['resources/views']);
        $exclude = array_map(
            fn (string $path): string => str_replace('\\', '/', base_path($path)),
            (array) config('locales.scan.blade.exclude', []),
        );

        $entries = [];
        $skipped = [];
        $files = 0;

        foreach ($roots as $root) {
            $directory = base_path($root);

            if (! File::isDirectory($directory)) {
                $this->warn('مسار غير موجود: '.$root);

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
                $files++;

                foreach ($translator->extract((string) $file->getContents(), $relative) as $key => $contexts) {
                    $entries[$key]['type'] = 'blade';
                    $entries[$key]['contexts'] = array_values(array_unique(
                        array_merge($entries[$key]['contexts'] ?? [], $contexts),
                    ));
                }

                foreach ($translator->skipped() as $note) {
                    $skipped[] = $note;
                }
            }
        }

        /*
         * نصوص ملفات الإعداد المعروضة: تدخل الكتالوج من هنا، وتُترجَم عند
         * القراءة عبر `TranslatedConfig`. لا تُغلَّف في مكانها لأن `__()`
         * داخل ملف إعداد تُنفَّذ عند الإقلاع وتُخبَز مع `config:cache`.
         */
        $configFiles = 0;

        foreach ((array) config('locales.scan.config.files', []) as $relative) {
            $absolute = base_path((string) $relative);

            if (! File::exists($absolute)) {
                $this->warn('ملف إعداد غير موجود: '.$relative);

                continue;
            }

            $configFiles++;

            foreach ($this->stringsIn(require $absolute) as $string) {
                $entries[$string]['type'] = 'config';
                $entries[$string]['contexts'] = array_values(array_unique(
                    array_merge($entries[$string]['contexts'] ?? [], [(string) $relative]),
                ));
            }
        }

        /*
         * ما غُلّف فعلًا في شيفرة PHP: `__('نصّ')` صريحة.
         *
         * القراءة بالمكتوب لا بالمحتمَل: المسح هنا لا يبحث عن «كل سلسلة
         * عربية» — أكثرها في `app/` ليس واجهة أصلًا (برومبتات، وأمثلة،
         * ومخرجات أوامر) — بل عمّا مرّ بقرار بشري عبر `i18n:wrap-php`.
         */
        $phpStrings = 0;

        foreach ((array) config('locales.scan.php.roots', []) as $root) {
            foreach ($this->wrappedStrings($root) as $string) {
                $phpStrings++;
                $entries[$string]['type'] ??= 'php';
                $entries[$string]['contexts'] = array_values(array_unique(
                    array_merge($entries[$string]['contexts'] ?? [], [$root]),
                ));
            }
        }

        /*
         * نصوص JavaScript: `t('نصّ')` في `resources/js`.
         *
         * تدخل الكتالوج كغيرها فتُترجَم في الدفعة نفسها، وتُكتب مفاتيحها
         * في ملف منفصل ليعرف القالب ما يرسله إلى المتصفح. بلا هذا الملف
         * كان الخيار إمّا إرسال القاموس كاملًا في كل صفحة — ١٠٠ ألف حرف
         * على كل طلب — أو ترك نصوص JS بلا ترجمة، وقد كانت متروكة فعلًا.
         */
        $jsKeys = [];

        foreach ((array) config('locales.scan.js.roots', []) as $root) {
            foreach ($this->jsStrings($root) as $string) {
                $jsKeys[] = $string;
                $entries[$string]['type'] ??= 'js';
                $entries[$string]['contexts'] = array_values(array_unique(
                    array_merge($entries[$string]['contexts'] ?? [], [$root]),
                ));
            }
        }

        $jsKeys = array_values(array_unique($jsKeys));
        sort($jsKeys);

        $this->components->twoColumnDetail('قوالب مفحوصة', (string) $files);
        $this->components->twoColumnDetail('ملفات إعداد مفحوصة', (string) $configFiles);
        $this->components->twoColumnDetail('نصوص مغلّفة في PHP', (string) $phpStrings);
        $this->components->twoColumnDetail('نصوص مغلّفة في JavaScript', (string) count($jsKeys));
        $this->components->twoColumnDetail('نصوص فريدة', (string) count($entries));
        $this->components->twoColumnDetail(
            'حروف تُترجَم',
            (string) array_sum(array_map('mb_strlen', array_keys($entries))),
        );
        $this->components->twoColumnDetail('كتل متروكة بلا معالجة', (string) count($skipped));

        if ($skipped !== [] && $this->output->isVerbose()) {
            $this->newLine();
            $this->line('كتل لم تُغلَّف (تحتاج معالجة يدوية):');

            foreach ($skipped as $note) {
                $this->line('  · ['.$note['reason'].'] '.$note['path'].' — '.$note['snippet']);
            }
        }

        if ($this->option('dry')) {
            $this->components->info('عرض فقط — لم يُكتب الكتالوج.');

            return self::SUCCESS;
        }

        $previous = array_keys($catalog->source());
        $catalog->writeSource($entries);

        $keysPath = base_path((string) config('locales.scan.js.keys', 'lang/_source/js-keys.json'));
        File::ensureDirectoryExists(dirname($keysPath));
        File::put($keysPath, json_encode($jsKeys, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL);

        $added = count(array_diff(array_keys($entries), $previous));
        $removed = count(array_diff($previous, array_keys($entries)));

        $this->components->info("كُتب الكتالوج: +{$added} نصًّا جديدًا، -{$removed} نصًّا لم يعد مستعملًا.");
        $this->line('  '.$catalog->sourcePath());

        return self::SUCCESS;
    }

    /**
     * السلاسل العربية الممرَّرة إلى `__()` أو `trans()` في شجرة PHP.
     *
     * @return array<int, string>
     */
    private function wrappedStrings(string $root): array
    {
        $directory = base_path($root);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $exclude = array_map(
            fn (string $path): string => str_replace('\\', '/', base_path($path)),
            (array) config('locales.scan.php.exclude', []),
        );

        $found = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
            $absolute = str_replace('\\', '/', $file->getPathname());

            foreach ($exclude as $excluded) {
                if (str_starts_with($absolute, $excluded)) {
                    continue 2;
                }
            }

            $code = (string) $file->getContents();

            if (! str_contains($code, '__(') && ! str_contains($code, 'trans(')) {
                continue;
            }

            preg_match_all(
                "/\b(?:__|trans)\(\s*'((?:[^'\\\\]|\\\\.)*)'/u",
                $code,
                $matches,
            );

            foreach ($matches[1] as $literal) {
                $value = str_replace(["\\'", '\\\\'], ["'", '\\'], $literal);

                if (preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1) {
                    $found[] = $value;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * السلاسل العربية الممرَّرة إلى `t()` في شجرة JavaScript.
     *
     * القراءة بالمكتوب لا بالمحتمَل، تمامًا كنظيرتها في PHP: ما لم يمرّ
     * بقرار كاتب الملف لا يُترجَم.
     *
     * @return array<int, string>
     */
    private function jsStrings(string $root): array
    {
        $directory = base_path($root);

        if (! File::isDirectory($directory)) {
            return [];
        }

        $found = [];

        foreach (Finder::create()->files()->in($directory)->name('*.js') as $file) {
            $code = (string) $file->getContents();

            if (! str_contains($code, 't(')) {
                continue;
            }

            /*
             * `\b` قبل `t` لا يكفي: `format(` و`await(` تنتهي بـ`t`. الشرط
             * أن يسبقها فاصل حقيقي — بداية سطر أو مسافة أو قوس أو معامل.
             */
            preg_match_all(
                '/(?:^|[^\w$.])t\(\s*([\'"])((?:[^\\\\]|\\\\.)*?)\1/u',
                $code,
                $matches,
            );

            foreach ($matches[2] as $literal) {
                $value = str_replace(['\\\'', '\\"', '\\\\'], ["'", '"', '\\'], $literal);

                if (preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1) {
                    $found[] = $value;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * كل نصّ عربي في شجرة إعداد، مهما عمق تشعّبها.
     *
     * @return array<int, string>
     */
    private function stringsIn(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return preg_match('/[\x{0600}-\x{06FF}]/u', $trimmed) === 1 ? [$trimmed] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $found = [];

        // القيم وحدها: المفتاح عقدٌ يقرأه القالب بالاسم، وترجمته تُفرغ
        // الصفحة بلا خطأ واحد في السجل.
        foreach ($value as $item) {
            $found = array_merge($found, $this->stringsIn($item));
        }

        return $found;
    }
}
