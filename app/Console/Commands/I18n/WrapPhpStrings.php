<?php

namespace App\Console\Commands\I18n;

use App\Modules\Shared\I18n\PhpSourceTranslator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\Finder;

/**
 * تغليف السلاسل المعروضة في شيفرة PHP بـ `__()`.
 *
 * لا يُشغَّل على `app/` كلها بقصد: أكثر من نصف السلاسل العربية هناك ليست
 * واجهة — برومبتات تُرسَل للنماذج، وأمثلة ذهبية، ومخرجات أوامر سطرية،
 * ومعاجم مطابقة. ترجمة أيٍّ منها ليست تحسينًا بل عطل. لذلك المسار
 * مطلوب صراحةً في كل تشغيل، والقرار قرار بشري لا مسح شامل.
 *
 * ضمانة السلامة: كل ملف يُفحص بـ`php -l` بعد التعديل، وأي ملف يفشل
 * يُعاد إلى أصله ويُذكر بالاسم. الخطأ المتوقع الوحيد هو تعبير ثابت
 * (`const`، قيمة افتراضية) وهو خطأ تصريف يلتقطه الفحص دائمًا.
 */
final class WrapPhpStrings extends Command
{
    protected $signature = 'i18n:wrap-php
                            {path* : المسارات المطلوبة نسبةً إلى جذر المشروع}
                            {--dry : اعرض ما سيتغيّر دون كتابة}';

    protected $description = 'تغليف السلاسل العربية المعروضة في شيفرة PHP بدالة الترجمة';

    public function handle(PhpSourceTranslator $translator): int
    {
        $changed = [];
        $reverted = [];
        $concatenated = [];
        $wrapped = 0;

        $protected = $this->protectedFiles();
        $refused = [];

        foreach ($this->argument('path') as $path) {
            foreach ($this->files($path) as $file) {
                $normalized = str_replace('\\', '/', $file);
                $blocked = false;

                foreach ($protected as $prefix) {
                    if ($normalized === $prefix || str_starts_with($normalized, rtrim($prefix, '/').'/')) {
                        $blocked = true;

                        break;
                    }
                }

                if ($blocked) {
                    $refused[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $normalized);

                    continue;
                }

                $original = (string) File::get($file);

                if (! preg_match('/[\x{0600}-\x{06FF}]/u', $original)) {
                    continue;
                }

                $translator->reset();
                $rewritten = $translator->rewrite($original);
                $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $file));

                foreach ($translator->concatenated() as $fragment) {
                    $concatenated[] = $relative.' — '.$fragment;
                }

                if ($rewritten === $original) {
                    continue;
                }

                $count = array_sum($translator->collected());

                if ($this->option('dry')) {
                    $changed[$relative] = $count;
                    $wrapped += $count;

                    continue;
                }

                File::put($file, $rewritten);

                if ($this->lints($file)) {
                    $changed[$relative] = $count;
                    $wrapped += $count;

                    continue;
                }

                File::put($file, $original);
                $reverted[] = $relative;
            }
        }

        $this->components->twoColumnDetail('ملفات معدّلة', (string) count($changed));
        $this->components->twoColumnDetail(
            'ملفات محميّة تُخطّت',
            $refused === [] ? '<fg=green>0</>' : '<fg=yellow>'.count($refused).'</>',
        );
        $this->components->twoColumnDetail('سلاسل مغلّفة', (string) $wrapped);
        $this->components->twoColumnDetail('ملفات أُعيدت لأصلها', $reverted === [] ? '<fg=green>0</>' : '<fg=red>'.count($reverted).'</>');
        $this->components->twoColumnDetail(
            'شظايا موصولة تحتاج نائبًا',
            $concatenated === [] ? '<fg=green>0</>' : '<fg=yellow>'.count($concatenated).'</>',
        );

        if ($concatenated !== []) {
            $this->newLine();
            $this->line('  نصوص تُوصَل بمتغيّر — حوّلها إلى نائب يدويًّا قبل ترجمتها:');

            foreach (array_slice($concatenated, 0, 25) as $fragment) {
                $this->line('    · '.$fragment);
            }

            if (count($concatenated) > 25) {
                $this->line('    … و'.(count($concatenated) - 25).' غيرها.');
            }
        }

        if ($this->output->isVerbose()) {
            foreach ($changed as $relative => $count) {
                $this->line('  · '.$relative.' ('.$count.')');
            }
        }

        foreach ($reverted as $relative) {
            $this->line('  <fg=red>!</> '.$relative.' — التغليف كسر التصريف، ويحتاج معالجة يدوية.');
        }

        if ($refused !== []) {
            $this->newLine();
            $this->line('  محميّة في <fg=cyan>locales.scan.php.never_wrap</> — برومبتات ومعاجم لا واجهة:');

            foreach ($refused as $relative) {
                $this->line('    · '.$relative);
            }
        }

        if ($this->option('dry')) {
            $this->components->info('عرض فقط — لم يُكتب شيء.');
        } else {
            $this->components->warn('شغّل `php artisan i18n:extract` ثم `i18n:translate` لتصل هذه النصوص إلى ملفات اللغات.');
        }

        return $reverted === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * المسارات المحميّة بصيغة مطلقة — ملفًّا كانت أو مجلدًا.
     *
     * مصدران لا واحد:
     *   · `never_wrap` — برومبتات ومعاجم مطابقة، ترجمتها تغيّر ما يُطلب من
     *     النموذج أو تكسر القياس نفسه.
     *   · `scan.php.exclude` — ما أُعلن أصلًا أنه خارج الترجمة، ومنه مخرجات
     *     PDF التي تبقى عربية بقرار. كانت هذه القائمة تحرس **الاستخراج**
     *     وحده، فمرّ `wrap-php` فوقها وغلّف `ProfilePdfController` — أي غيّر
     *     اسم ملفٍ يُنزَّل. حارسٌ يحمي بابًا ويترك الآخر ليس حارسًا.
     *
     * @return array<int, string>
     */
    private function protectedFiles(): array
    {
        $paths = array_merge(
            (array) config('locales.scan.php.never_wrap', []),
            (array) config('locales.scan.php.exclude', []),
        );

        return array_values(array_unique(array_map(
            fn (string $path): string => str_replace('\\', '/', base_path($path)),
            $paths,
        )));
    }

    /**
     * @return array<int, string>
     */
    private function files(string $path): array
    {
        $absolute = base_path($path);

        if (File::isFile($absolute)) {
            return [$absolute];
        }

        if (! File::isDirectory($absolute)) {
            $this->components->warn('مسار غير موجود: '.$path);

            return [];
        }

        $files = [];

        foreach (Finder::create()->files()->in($absolute)->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function lints(string $file): bool
    {
        return Process::run([PHP_BINARY, '-l', $file])->successful();
    }
}
