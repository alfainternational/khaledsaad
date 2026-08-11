<?php

namespace App\Console\Commands\I18n;

use App\Modules\Shared\I18n\AiTranslator;
use App\Modules\Shared\I18n\LocaleRegistry;
use App\Modules\Shared\I18n\TranslationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * خبز الترجمات: يحوّل كتالوج المصدر إلى `lang/<locale>.json` مرة واحدة.
 *
 * تزايُديّ بالضرورة لا بالتحسين: كل نصّ يُترجَم مرة واحدة في عمره ثم
 * يُقرأ من الملف إلى الأبد. إعادة تشغيل الأمر بعد إضافة عشرة نصوص
 * تترجم عشرة، لا ألفين. وهذا ما يجعل تكلفة اللغة الجديدة معروفة سلفًا
 * وقابلة للقياس قبل الالتزام بها.
 *
 * `--force` وحده يعيد ترجمة ما تُرجم، ولا يمسّ ما راجعه إنسان: السطر
 * الذي صحّحه مترجم بشري أثمن من أي مخرج نموذج، فلا يُدهَس بأمر عابر.
 *
 * التوازي (`--jobs`) ليس ترفًا: الدفعة الواحدة تستغرق نحو دقيقة، وواجهة
 * بألفي نصّ تعني ساعتين للغة الواحدة على خيط واحد. ولغة تكلّف ساعتين
 * لغة لا تُضاف.
 */
final class BuildTranslations extends Command
{
    protected $signature = 'i18n:translate
                            {--locale=* : اللغات المطلوبة (الافتراضي: كل المفعّلة عدا العربية)}
                            {--force : أعد ترجمة النصوص المترجَمة آليًا أيضًا}
                            {--limit=0 : حدّ أقصى لعدد النصوص في هذا التشغيل}
                            {--jobs=4 : عدد العمليات المتوازية}
                            {--prune : احذف الترجمات التي لم يعد نصّها موجودًا في الشيفرة}
                            {--slice= : داخلي — الشريحة المسندة لهذه العملية بصيغة i:n}';

    protected $description = 'ترجمة كتالوج النصوص إلى ملفات ثابتة تُشحن مع الكود';

    public function handle(
        LocaleRegistry $locales,
        TranslationCatalog $catalog,
        AiTranslator $translator,
    ): int {
        $source = $catalog->source();

        if ($source === []) {
            $this->components->error('الكتالوج فارغ. شغّل `php artisan i18n:extract` أولًا.');

            return self::FAILURE;
        }

        $requested = (array) $this->option('locale');
        $targets = $requested === [] ? $locales->targets() : $requested;
        $exit = self::SUCCESS;

        foreach ($targets as $locale) {
            if (! $locales->isEnabled($locale) || $locale === $locales->source()) {
                $this->components->warn("تخطّي «{$locale}»: غير مفعّلة أو أنها لغة المصدر.");

                continue;
            }

            $exit = max($exit, $this->buildLocale($locale, $source, $catalog, $translator));
        }

        return $exit;
    }

    /**
     * @param  array<string, array{contexts: array<int, string>, type: string}>  $source
     */
    private function buildLocale(
        string $locale,
        array $source,
        TranslationCatalog $catalog,
        AiTranslator $translator,
    ): int {
        $existing = $catalog->translations($locale);
        $provenance = $catalog->provenance($locale);

        if ($this->option('prune') && $this->option('slice') === null) {
            $orphans = $catalog->orphans($locale);

            foreach ($orphans as $orphan) {
                unset($existing[$orphan], $provenance[$orphan]);
            }

            if ($orphans !== []) {
                $catalog->writeTranslations($locale, $existing);
                $catalog->writeProvenance($locale, $provenance);
                $this->components->info("«{$locale}»: حُذفت ".count($orphans).' ترجمة لنصوص لم تعد في الشيفرة.');
            }
        }

        $pending = $this->pending($source, $existing, $provenance);

        if ($pending === []) {
            $this->components->info("«{$locale}»: لا جديد — ".count($existing).' نص مترجَم.');

            return self::SUCCESS;
        }

        $slice = $this->option('slice');

        if (is_string($slice) && $slice !== '') {
            return $this->runSlice($locale, $source, $pending, $slice, $translator);
        }

        $jobs = max(1, (int) $this->option('jobs'));
        $characters = array_sum(array_map('mb_strlen', $pending));

        $this->components->info("«{$locale}»: ".count($pending)." نصًّا ({$characters} حرفًا) على {$jobs} عملية.");

        $results = $jobs > 1
            ? $this->runInParallel($locale, $jobs)
            : $this->translateChunk($pending, $locale, $source, $translator, showProgress: true);

        foreach ($results['translations'] as $key => $value) {
            // شرط `isset($source[$key])` يمنع تسرّب مفتاح من كتالوج قديم
            // في عملية فرعية بدأت قبل إعادة استخراج.
            if (! isset($source[$key])) {
                continue;
            }

            $existing[$key] = $value;
            $provenance[$key] = [
                'model' => 'ai',
                'at' => now()->toIso8601String(),
                'reviewed' => $provenance[$key]['reviewed'] ?? false,
            ];
        }

        $catalog->writeTranslations($locale, $existing);
        $catalog->writeProvenance($locale, $provenance);

        $this->newLine();
        $this->components->twoColumnDetail("«{$locale}» ترجمات جديدة", (string) count($results['translations']));
        $this->components->twoColumnDetail("«{$locale}» إجمالي مترجَم", (string) count($existing));

        if ($results['failures'] === []) {
            return self::SUCCESS;
        }

        $this->components->twoColumnDetail("«{$locale}» فشل", '<fg=red>'.count($results['failures']).'</>');
        $this->newLine();

        foreach (array_slice($results['failures'], 0, 15, true) as $key => $reasons) {
            $this->line('  · '.mb_substr($key, 0, 70).' — '.implode('؛ ', (array) $reasons));
        }

        if (count($results['failures']) > 15) {
            $this->line('  … و'.(count($results['failures']) - 15).' غيرها.');
        }

        // فشل جزئي ليس نجاحًا: أي نصّ بلا ترجمة يعني شاشة مختلطة.
        return self::FAILURE;
    }

    /**
     * @param  array<string, array{contexts: array<int, string>, type: string}>  $source
     * @param  array<string, string>  $existing
     * @param  array<string, array<string, mixed>>  $provenance
     * @return array<int, string>
     */
    private function pending(array $source, array $existing, array $provenance): array
    {
        $pending = [];

        foreach (array_keys($source) as $key) {
            $translated = isset($existing[$key]) && trim($existing[$key]) !== '';

            if (! $translated) {
                $pending[] = $key;

                continue;
            }

            // المراجَع بشريًّا لا يُعاد إنتاجه ولو بـ`--force`.
            if ($this->option('force') && ($provenance[$key]['reviewed'] ?? false) !== true) {
                $pending[] = $key;
            }
        }

        $limit = (int) $this->option('limit');

        return $limit > 0 ? array_slice($pending, 0, $limit) : $pending;
    }

    /**
     * عملية فرعية: تترجم شريحتها وتكتبها في ملف مؤقت خاص بها.
     *
     * الكتابة في ملف منفصل لا في `lang/<locale>.json` مقصودة: أربع عمليات
     * تكتب في ملف واحد تدهس بعضها، وتضيع ترجمات دُفع ثمنها فعلًا دون أن
     * يفشل شيء ظاهر.
     *
     * @param  array<string, array{contexts: array<int, string>, type: string}>  $source
     * @param  array<int, string>  $pending
     */
    private function runSlice(
        string $locale,
        array $source,
        array $pending,
        string $slice,
        AiTranslator $translator,
    ): int {
        [$index, $total] = array_map('intval', explode(':', $slice) + [1 => 1]);

        $mine = array_values(array_filter(
            $pending,
            fn (string $key, int $position): bool => $position % max(1, $total) === $index,
            ARRAY_FILTER_USE_BOTH,
        ));

        File::ensureDirectoryExists($this->partialDirectory());
        $path = $this->partialPath($locale, $index);

        /*
         * الكتابة بعد كل دفعة لا في النهاية: شريحة من ستّ عشرة دفعة قد
         * تنقطع في الخامسة عشرة، فتضيع أربع عشرة دفعة دُفع ثمنها. وهذا
         * ما وقع فعلًا في أول بناء للفرنسية: ستّ شرائح ماتت بلا ملف
         * واحد، وضاع كل ما تُرجم فيها.
         */
        $persist = function (array $result) use ($path): void {
            File::put($path, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        };

        $persist(['translations' => [], 'failures' => []]);

        $this->translateChunk($mine, $locale, $source, $translator, showProgress: false, onBatch: $persist);

        return self::SUCCESS;
    }

    /**
     * @return array{translations: array<string, string>, failures: array<string, mixed>}
     */
    private function runInParallel(string $locale, int $jobs): array
    {
        File::ensureDirectoryExists($this->partialDirectory());

        for ($index = 0; $index < $jobs; $index++) {
            File::delete($this->partialPath($locale, $index));
        }

        $commands = [];

        for ($index = 0; $index < $jobs; $index++) {
            $arguments = [
                PHP_BINARY, base_path('artisan'), 'i18n:translate',
                '--locale='.$locale,
                '--slice='.$index.':'.$jobs,
                '--jobs=1',
            ];

            if ($this->option('force')) {
                $arguments[] = '--force';
            }

            if ((int) $this->option('limit') > 0) {
                $arguments[] = '--limit='.(int) $this->option('limit');
            }

            $commands[] = $arguments;
        }

        $this->line('  تشغيل '.$jobs.' عملية متوازية…');

        // بلا مهلة: الدفعة الواحدة قد تتجاوز الدقيقة، والشريحة عشرات
        // الدفعات. المهلة الحقيقية داخل عميل HTTP لكل استدعاء.
        Process::pool(function ($pool) use ($commands): void {
            foreach ($commands as $arguments) {
                $pool->path(base_path())->timeout(0)->command($arguments);
            }
        })->start()->wait();

        $translations = [];
        $failures = [];

        for ($index = 0; $index < $jobs; $index++) {
            $path = $this->partialPath($locale, $index);

            if (! File::exists($path)) {
                $this->components->warn("الشريحة {$index} لم تُنتج ملفًا — راجع سجل الأخطاء.");

                continue;
            }

            $payload = json_decode((string) File::get($path), true);

            if (is_array($payload)) {
                $translations += (array) ($payload['translations'] ?? []);
                $failures += (array) ($payload['failures'] ?? []);
            }

            File::delete($path);
        }

        return ['translations' => $translations, 'failures' => $failures];
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, array{contexts: array<int, string>, type: string}>  $source
     * @return array{translations: array<string, string>, failures: array<string, mixed>}
     */
    private function translateChunk(
        array $keys,
        string $locale,
        array $source,
        AiTranslator $translator,
        bool $showProgress,
        ?callable $onBatch = null,
    ): array {
        $contexts = [];

        foreach ($keys as $key) {
            $contexts[$key] = $source[$key]['contexts'] ?? [];
        }

        $batchSize = max(1, (int) config('locales.build.batch', 24));
        $translations = [];
        $failures = [];
        $bar = $showProgress ? $this->output->createProgressBar(count($keys)) : null;
        $bar?->start();

        foreach (array_chunk($keys, $batchSize) as $batch) {
            $result = $translator->translate($batch, $locale, $contexts);

            $translations += $result['translations'];
            $failures += $result['failures'];

            if ($onBatch !== null) {
                $onBatch(['translations' => $translations, 'failures' => $failures]);
            }

            $bar?->advance(count($batch));
        }

        $bar?->finish();

        return ['translations' => $translations, 'failures' => $failures];
    }

    private function partialDirectory(): string
    {
        return storage_path('framework/i18n');
    }

    private function partialPath(string $locale, int $index): string
    {
        return $this->partialDirectory().'/'.$locale.'-'.$index.'.json';
    }
}
