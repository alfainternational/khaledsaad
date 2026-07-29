<?php

namespace Tests\Feature;

use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use Tests\TestCase;

/**
 * بوابات المعمارية: القواعد التي لا تُحرس باختبار تُخترق خلال أسابيع.
 *
 * كل اختبار هنا يقابل قاعدة في CLAUDE.md §٨ أو §١٤. فشله ليس «اختبارًا
 * أحمر» بل مخالفة معمارية يجب إصلاحها لا تجاوزها.
 */
class ArchitectureBoundariesTest extends TestCase
{
    private const MODULES_PATH = 'app/Modules';

    /**
     * §٨: Diagnosis وMeasurement لا يتصلان بالإنترنت.
     *
     * السبب: الدرجة يجب أن تكون قابلة لإعادة الحساب من لقطة قاعدة بيانات.
     * استدعاء شبكي واحد داخلهما يجعل درجتين بنفس المدخلات مختلفتين، فتنهار
     * المقارنة الزمنية وتُصبح التنبيهات ضوضاء.
     */
    public function test_diagnosis_and_measurement_do_not_reach_the_network(): void
    {
        $forbidden = ['Http::', 'file_get_contents(\'http', 'curl_init', 'GuzzleHttp', 'Illuminate\\Support\\Facades\\Http'];

        foreach (['Diagnosis', 'Measurement'] as $module) {
            $path = base_path(self::MODULES_PATH."/{$module}");

            // الحدّ يجب أن يوجد كمجلد قبل أن يوجد كقاعدة: وحدة غير منشأة
            // تجعل الفحص يمرّ صامتًا على لا شيء.
            $this->assertDirectoryExists($path, "حدّ الوحدة {$module} غير منشأ.");

            foreach ($this->phpFilesIn($path) as $file) {
                $code = $this->codeOf($file);

                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $code,
                        "اتصال شبكي داخل {$module}: ".$this->relative($file)
                        .' — الحساب يقرأ من قاعدة البيانات فقط.',
                    );
                }
            }
        }
    }

    /**
     * §٨: PlatformBridge حدّه القدرات القديمة، لا الفوترة والصلاحيات.
     *
     * Entitlements وFeatureKey هما الواجهة أصلًا، ويحرس وسيط feature عشرات
     * المسارات. لفّهما بجسر يضيف طبقة بلا حدّ خارجي تحرسه.
     */
    public function test_platform_bridge_does_not_wrap_billing_or_entitlements(): void
    {
        $forbidden = ['Entitlements', 'Subscription', 'CreditWallet', 'FeatureKey'];
        $path = base_path(self::MODULES_PATH.'/PlatformBridge');

        $this->assertDirectoryExists($path, 'حدّ PlatformBridge غير منشأ.');

        foreach ($this->phpFilesIn($path) as $file) {
            $code = $this->codeOf($file);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    'الفوترة داخل PlatformBridge: '.$this->relative($file)
                    .' — تُستدعى مباشرة لا عبر الجسر.',
                );
            }
        }
    }

    /**
     * §١٤: ممنوع حساب مقياس في Blade أو Controller.
     *
     * المقياس المحسوب في الواجهة لا يمكن اختباره ولا إعادة إنتاجه، ويتفرّع
     * إلى نسخ متباينة في كل شاشة.
     */
    public function test_metrics_are_not_computed_in_controllers_or_views(): void
    {
        $paths = [base_path('app/Http/Controllers'), resource_path('views')];
        $keys = MetricKey::all();

        foreach ($paths as $path) {
            foreach ($this->filesIn($path, ['php', 'blade.php']) as $file) {
                $contents = $this->codeOf($file);

                foreach ($keys as $key) {
                    // العرض مسموح؛ المحظور هو الإسناد أي الحساب في مكانه.
                    $this->assertDoesNotMatchRegularExpression(
                        '/\$'.preg_quote($key, '/').'\s*=[^=]/',
                        $contents,
                        'حساب مقياس خارج Diagnosis/Measurement: '.$this->relative($file)
                        ." ({$key})",
                    );
                }
            }
        }
    }

    /**
     * §٤.١: مفردة دليل واحدة، لا رابع لها.
     */
    public function test_evidence_level_has_exactly_three_values(): void
    {
        $this->assertSame(
            ['measured', 'derived', 'inferred'],
            EvidenceLevel::values(),
            'تدرّج الدليل ثلاث قيم بالضبط — أي إضافة تعيد تشتّت المفردات.',
        );
    }

    /**
     * §١٥: لا يُحوَّل inferred إلى measured.
     *
     * الدمج يأخذ أضعف المدخلات دائمًا: حساب مبنيّ على فرضية يظل فرضية مهما
     * كانت دقة معادلته.
     */
    public function test_mixing_evidence_levels_takes_the_weakest(): void
    {
        $this->assertSame(
            EvidenceLevel::Inferred,
            EvidenceLevel::weakest([EvidenceLevel::Measured, EvidenceLevel::Inferred]),
        );

        $this->assertSame(
            EvidenceLevel::Derived,
            EvidenceLevel::weakest([EvidenceLevel::Measured, EvidenceLevel::Derived]),
        );

        // لا مدخل يعني «لا نعرف»، لا «مؤكد».
        $this->assertSame(EvidenceLevel::Inferred, EvidenceLevel::weakest([]));
    }

    /**
     * §١٢: لا مرادف لاسم مقياس.
     *
     * base_score كان الاسم الداخلي القديم ولا يقابل شيئًا في المواصفة. يُسمح
     * به مؤقتًا في الأدوات القديمة، ويُمنع منعًا باتًا داخل app/Modules.
     */
    public function test_modules_do_not_use_legacy_metric_names(): void
    {
        foreach ($this->phpFilesIn(base_path(self::MODULES_PATH)) as $file) {
            $this->assertStringNotContainsString(
                'base_score',
                $this->codeOf($file),
                'اسم مقياس قديم داخل الوحدات: '.$this->relative($file)
                .' — استخدم MetricKey::AXIS_SCORE أو MATURITY_SCORE.',
            );
        }
    }

    /**
     * §٨: لا وحدة باسم Planning. توليد الخطط قدرة قديمة خلف PlatformBridge.
     */
    public function test_there_is_no_planning_module(): void
    {
        $this->assertDirectoryDoesNotExist(
            base_path(self::MODULES_PATH.'/Planning'),
            'الخطة نتيجة تابعة للتشخيص، لا قدرة مستقلة تُسوَّق.',
        );
    }

    /**
     * كل جامع أو خدمة وحدة له مستدعٍ في كود الإنتاج.
     *
     * ثلاث مرات في جلسة واحدة بُنيت قدرة صحيحة وخضراء الاختبار ولم يستدعها
     * شيء: `LegacyCapabilities` (فظلّت الخطط تُولَّد من وصف المستخدم لنفسه)،
     * والاستقبال الصوتي (مسار بلا زر)، و`OwnedAssetsCollector` (فلم يُقَس
     * المحور الثامن إطلاقًا).
     *
     * العطل صامت لأن اختبار الوحدة يستدعيها مباشرة فيمرّ أخضر، بينما لا
     * يبلغها مستخدم أبدًا. **القدرة التي لا يستدعيها كود إنتاج غير موجودة.**
     *
     * الفحص على الأصناف القابلة للاستدعاء وحدها: النماذج والعقود والقيم
     * تُستعمل بأسمائها في مواضع أخرى، والـenum يُقرأ ولا «يُستدعى».
     */
    public function test_every_module_service_has_a_production_caller(): void
    {
        /*
         * البذر جذرٌ ثالث: `db:seed` كود إنتاج يُشغَّل على الخادم، وبانٍ
         * يستدعيه البذر وحده موصولٌ فعلًا لا يتيم.
         */
        $roots = [base_path('app'), base_path('routes'), base_path('database/seeders')];
        $orphans = [];

        foreach ($this->phpFilesIn(base_path(self::MODULES_PATH)) as $file) {
            $class = basename($file, '.php');

            // النماذج والعقود والقيم والمهام خارج الفحص: لا تُستدعى بالاسم
            // من خدمة، بل تُنشأ أو تُنفَّذ أو تُوسَّع.
            if (preg_match('#[\\\\/](Models|Contracts|Exceptions|Jobs)[\\\\/]#', $file)) {
                continue;
            }

            $code = $this->codeOf($file);

            if (! str_contains($code, 'class '.$class)) {
                continue;
            }

            $callers = 0;

            foreach ($roots as $root) {
                foreach ($this->phpFilesIn($root) as $candidate) {
                    if (realpath($candidate) === realpath($file)) {
                        continue;
                    }

                    if (preg_match('/\b'.preg_quote($class, '/').'\b/', $this->codeOf($candidate))) {
                        $callers++;
                        break 2;
                    }
                }
            }

            if ($callers === 0) {
                $orphans[] = $this->relative($file);
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'قدرات بلا مستدعٍ في كود الإنتاج — مبنية ولا يبلغها مستخدم: '
            .implode(' · ', $orphans),
        );
    }

    /**
     * @return array<int, string>
     */
    private function phpFilesIn(string $path): array
    {
        return $this->filesIn($path, ['php']);
    }

    /**
     * كود الملف بلا تعليقات.
     *
     * القاعدة تخصّ ما يُنفَّذ لا ما يُشرح: أول تشغيل لهذه البوابة أسقطت
     * MetricKey نفسه، لأن شرحه يذكر الاسم القديم ليفسّر سبب منعه. فحص النص
     * الخام يجعل كل توثيق للقاعدة مخالفةً لها.
     */
    private function codeOf(string $file): string
    {
        $contents = (string) file_get_contents($file);

        if (! str_ends_with($file, '.php')) {
            return $contents;
        }

        $code = '';
        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function filesIn(string $path, array $extensions): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            foreach ($extensions as $extension) {
                if (str_ends_with($file->getFilename(), '.'.$extension)) {
                    $found[] = $file->getPathname();
                    break;
                }
            }
        }

        return $found;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
