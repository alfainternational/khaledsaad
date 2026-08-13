<?php

namespace App\Console\Commands\I18n;

use App\Modules\Shared\I18n\LocaleRegistry;
use App\Modules\Shared\I18n\PlaceholderGuard;
use App\Modules\Shared\I18n\ResidueScanner;
use App\Modules\Shared\I18n\TranslationCatalog;
use Illuminate\Console\Command;

/**
 * بوابة جودة الترجمة — تُشغَّل قبل النشر وتُرجع خروجًا غير صفري عند أي خلل.
 *
 * سبب وجودها: أعطال الترجمة صامتة كلها. النص الناقص يُعرض عربيًّا داخل
 * شاشة إنجليزية، والنائب المكسور يُعرض `:v1` خامًا، والترجمة المطابقة
 * للأصل تبدو ترجمةً وليست كذلك. لا شيء من هذا يرمي استثناءً ولا يظهر في
 * سجل. القياس هنا هو الطريقة الوحيدة لرؤيته قبل المستخدم.
 *
 * ─── لماذا لا يكفي قياس الكتالوج ───
 *
 * الأمر كان يقيس اكتمال `catalog.json` ويُسمّي الناتج «تغطية». وأعلن
 * ١٠٠٪ للغتين بينما ٣٦ شاشة تعرض عربيًّا داخل واجهة إنجليزية — لأن ما
 * لا يدخل الكتالوج أصلًا لا يظهر في عدّاد النقص. المقياس كان يقيس نفسه.
 *
 * فأُضيفت طبقتان يقيسهما `ResidueScanner`: ما بقي عربيًّا في القوالب
 * وحزمة JavaScript، ورسائل الإطار التي تُعرض كمفاتيح خام. الأولى كانت
 * ١٦٤ نصًّا، والثانية كانت مكسورة في العربية نفسها.
 */
final class AuditTranslations extends Command
{
    protected $signature = 'i18n:audit
                            {--locale=* : اللغات المفحوصة (الافتراضي: كل المفعّلة)}
                            {--strict : اعتبر النصوص الناقصة فشلًا لا تحذيرًا}';

    protected $description = 'فحص اكتمال الترجمات وسلامة النواب قبل النشر';

    public function handle(
        LocaleRegistry $locales,
        TranslationCatalog $catalog,
        PlaceholderGuard $guard,
        ResidueScanner $residue,
    ): int {
        $source = $catalog->source();

        if ($source === []) {
            $this->components->error('الكتالوج فارغ. شغّل `php artisan i18n:extract` أولًا.');

            return self::FAILURE;
        }

        $requested = (array) $this->option('locale');
        $targets = $requested === [] ? $locales->targets() : $requested;
        $failed = false;

        $this->components->twoColumnDetail('<options=bold>نصوص المصدر</>', (string) count($source));

        foreach ($targets as $locale) {
            $translations = $catalog->translations($locale);
            $provenance = $catalog->provenance($locale);

            $missing = $catalog->missing($locale);
            $orphans = $catalog->orphans($locale);
            $broken = [];
            $identical = [];
            $bloated = [];
            $reviewed = 0;

            foreach ($translations as $key => $value) {
                if (! isset($source[$key])) {
                    continue;
                }

                $violations = $guard->violations($key, $value);

                if ($violations !== []) {
                    $broken[$key] = $violations;
                }

                // اسم علامة أو منصّة يتطابق مع أصله بحقّ — لا يُعدّ فشلًا.
                if ($value === $key && ! $locales->isProtectedName($key)) {
                    $identical[] = $key;
                }

                /*
                 * تضخّم الطول: ليس خطأ لغويًّا بل خطأ واجهة. زر عرضه ثابت
                 * ونصّه صار ثلاثة أضعاف الأصل يكسر التخطيط، ولا يكشفه إلا
                 * فتح تلك الشاشة بتلك اللغة تحديدًا — وهو ما لا يحدث.
                 */
                if (mb_strlen($key) >= 12 && mb_strlen($value) > mb_strlen($key) * 2.2) {
                    $bloated[$key] = $value;
                }

                if (($provenance[$key]['reviewed'] ?? false) === true) {
                    $reviewed++;
                }
            }

            $coverage = count($source) === 0
                ? 100
                : round((count($source) - count($missing)) / count($source) * 100, 1);

            $this->newLine();
            $this->components->twoColumnDetail(
                "<options=bold>«{$locale}» — {$locales->nativeName($locale)}</>",
                $coverage.'% تغطية',
            );
            $this->components->twoColumnDetail('  مترجَم', (string) count($translations));
            $this->components->twoColumnDetail('  ناقص', $this->flag(count($missing)));
            $this->components->twoColumnDetail('  نواب مكسورة', $this->flag(count($broken)));
            $this->components->twoColumnDetail('  لم يُترجَم فعليًا', $this->flag(count($identical)));
            $this->components->twoColumnDetail('  طوله ضِعف الأصل', $this->flag(count($bloated), false));
            $this->components->twoColumnDetail('  زائد عن الحاجة', $this->flag(count($orphans), false));
            $this->components->twoColumnDetail('  راجعه إنسان', (string) $reviewed);

            if ($broken !== []) {
                $failed = true;
                $this->newLine();
                $this->line('  <fg=red>نواب مكسورة:</>');

                foreach (array_slice($broken, 0, 10, true) as $key => $violations) {
                    $this->line('    · '.mb_substr($key, 0, 60).' → '.implode('؛ ', $violations));
                }
            }

            if ($identical !== []) {
                $failed = true;
                $this->newLine();
                $this->line('  <fg=red>نصوص مطابقة للأصل:</>');

                foreach (array_slice($identical, 0, 10) as $key) {
                    $this->line('    · '.mb_substr($key, 0, 70));
                }
            }

            if ($missing !== [] && $this->option('strict')) {
                $failed = true;
            }

            if ($missing !== [] && $this->output->isVerbose()) {
                $this->newLine();
                $this->line('  نصوص بلا ترجمة:');

                foreach (array_slice($missing, 0, 30) as $key) {
                    $this->line('    · '.mb_substr($key, 0, 70));
                }
            }
        }

        $failed = $this->reportResidue($residue) || $failed;
        $failed = $this->reportFrameworkLines($residue, $locales->enabled()) || $failed;

        $this->newLine();

        if ($failed) {
            $this->components->error('التدقيق فشل. لا تنشر قبل إصلاح ما سبق.');

            return self::FAILURE;
        }

        $this->components->info('التدقيق نظيف.');

        return self::SUCCESS;
    }

    /**
     * الطبقة التي لا يراها الكتالوج: نصّ عربي يصل الشاشة بلا مرور بالترجمة.
     */
    private function reportResidue(ResidueScanner $scanner): bool
    {
        $labels = [
            'attribute' => 'سمات معروضة بلا تغليف',
            'directive' => 'تسميات داخل @foreach/@include',
            'inline-script' => 'سلاسل داخل <script> في القوالب',
            'javascript' => 'سلاسل في حزمة JavaScript خارج t()',
        ];

        $found = $scanner->scan();

        $this->newLine();
        $this->components->twoColumnDetail(
            '<options=bold>مخلّفات عربية تصل الشاشة</>',
            $found === [] ? '<fg=green>0</>' : '<fg=red>'.array_sum(array_map('count', $found)).'</>',
        );

        foreach ($labels as $kind => $label) {
            $hits = $found[$kind] ?? [];

            $this->components->twoColumnDetail('  '.$label, $this->flag(count($hits)));

            foreach (array_slice($hits, 0, 8) as $hit) {
                $this->line('    · '.$hit['file'].':'.$hit['line'].' — '.$hit['text']);
            }

            if (count($hits) > 8) {
                $this->line('    … و'.(count($hits) - 8).' غيرها.');
            }
        }

        return $found !== [];
    }

    /**
     * رسائل الإطار: مكسورةً تُعرض كمفتاح خام تحت الحقل، وفي لغة المصدر أيضًا.
     *
     * @param  array<int, string>  $locales
     */
    private function reportFrameworkLines(ResidueScanner $scanner, array $locales): bool
    {
        $broken = $scanner->frameworkLines($locales);

        $this->newLine();
        $this->components->twoColumnDetail(
            '<options=bold>رسائل الإطار (تحقق · ترقيم · مصادقة)</>',
            $broken === [] ? '<fg=green>سليمة في كل اللغات</>' : '<fg=red>'.array_sum(array_map('count', $broken)).' مفتاحًا خامًّا</>',
        );

        foreach ($broken as $locale => $keys) {
            $this->line('    · «'.$locale.'» — '.implode('، ', array_slice($keys, 0, 6))
                .(count($keys) > 6 ? ' …' : ''));
        }

        return $broken !== [];
    }

    private function flag(int $count, bool $error = true): string
    {
        if ($count === 0) {
            return '<fg=green>0</>';
        }

        return $error ? "<fg=red>{$count}</>" : "<fg=yellow>{$count}</>";
    }
}
