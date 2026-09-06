<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * بوابات معمارية على الشجرة نفسها.
 *
 * لا تحتاج قاعدة بيانات ولا طلبًا: تقرأ الملفات وتمنع فئة العطل قبل أن
 * تُكتب. أرخص بوابة وأسرعها، ولذلك تُشغَّل أولًا.
 *
 * سياسة الإضافة: **كل عطل يُصلَح يضيف اختبارًا يمنع فئته لا حالته.**
 */
class ArchitectureGuardsTest extends TestCase
{
    /**
     * INV-2 — لا حساب مقياس في قالب.
     *
     * معادلةٌ في Blade لا اختبار لها ولا مصدر واحد، وتُنسخ إلى الشاشة
     * التالية بصيغة مختلفة قليلًا — فيرى المستخدم رقمين لشيء واحد. هذا
     * بالضبط ما أنتج «٥٦» و«٣٣» لمشروع واحد.
     */
    #[Test]
    public function no_blade_template_computes_a_metric(): void
    {
        $offenders = [];

        foreach ($this->blades() as $path) {
            $body = $this->withoutComments((string) file_get_contents($path));

            // تعبيرٌ داخل `style="…"` هندسةُ عرض لا قياس: عرضُ شريطٍ
            // بالبكسل لا يظهر رقمه لأحد ولا يُقارَن بشاشة أخرى.
            $body = (string) preg_replace('/style="[^"]*"/u', '', $body);

            /*
             * الممنوع هو حساب **مقياس**، لا كل حساب.
             *
             * عرضُ شريط تقدّم يحتاج تحويل نسبة إلى عرض بالبكسل، وهذا
             * حسابُ عرضٍ لا حسابُ قياس: لا يظهر رقمه للمستخدم ولا يُقارَن
             * بشاشة أخرى. الحارس يستهدف أسماء المقاييس في §١٢ وحدها،
             * وإلا صار يمنع رسم المخططات فيُعطَّل بعد أسبوع.
             */
            $metrics = 'score|coverage|_rate|share_of_voice|ratio|fitness|maturity';

            if (preg_match('/\{\{[^}]*(?:'.$metrics.')[^}]*[*\/][^}]*\}\}/u', $body)) {
                $offenders[] = $this->relative($path);
            }
        }

        $this->assertSame([], $offenders, "قوالب تحسب مقياسًا:\n".implode("\n", $offenders));
    }

    /**
     * INV-1 — الرصيد لا يُخصم ولا يُمنح خارج `CreditManager`.
     *
     * القراءة للعرض مسموحة؛ الممنوع هو الكتابة. عمودٌ يُزاد أو يُنقص من
     * متحكّم يجعل الدفتر يكذب بلا أن يشتكي أحد.
     */
    #[Test]
    public function nothing_outside_the_credit_manager_moves_a_balance(): void
    {
        $offenders = [];

        foreach ($this->phpFiles('app') as $path) {
            if (str_contains($path, 'Services'.DIRECTORY_SEPARATOR.'Billing')) {
                continue;
            }

            $body = (string) file_get_contents($path);

            if (preg_match('/->(?:increment|decrement)\(\s*[\'"]balance[\'"]/u', $body)) {
                $offenders[] = $this->relative($path);
            }
        }

        $this->assertSame([], $offenders, "كتابة رصيد خارج CreditManager:\n".implode("\n", $offenders));
    }

    /**
     * INV-3 — لا قيمة نطاق خام في قالب يراه المستخدم.
     *
     * `analysis_queued` و`site_and_social` مصطلحاتنا لا مصطلحاته. طباعتها
     * تُشعره أنه ينظر إلى داخل آلة لا إلى منتَج.
     */
    #[Test]
    public function no_customer_facing_template_prints_a_raw_domain_value(): void
    {
        $raw = [
            'analysis_queued', 'site_and_social', 'biweekly', 'awareness',
            'irregular', 'provider_unavailable', 'insufficient_credits',
        ];

        $offenders = [];

        foreach ($this->blades('app') as $path) {
            $body = $this->withoutComments((string) file_get_contents($path));

            foreach ($raw as $value) {
                // داخل تعبير عرض `{{ }}` وحده: القيمة في `@if` مقارنةٌ لا طباعة.
                if (preg_match('/\{\{[^}]*[\'"]'.preg_quote($value, '/').'[\'"][^}]*\}\}/u', $body)) {
                    $offenders[] = $this->relative($path).' → '.$value;
                }
            }
        }

        $this->assertSame([], $offenders, "قيم خام في الواجهة:\n".implode("\n", $offenders));
    }

    /**
     * ولا رسالة خطأ تُكتب نصًّا داخل متحكّم: مكانها `FailureClassifier`
     * وحده، وإلا عاد لكل مسارٍ صياغتُه ونبرتُه (§10).
     */
    #[Test]
    public function no_controller_writes_its_own_credit_failure_copy(): void
    {
        $offenders = [];

        foreach ($this->phpFiles('app/Http/Controllers') as $path) {
            $body = (string) file_get_contents($path);

            if (str_contains($body, 'رصيدك غير كافٍ')) {
                $offenders[] = $this->relative($path);
            }
        }

        $this->assertSame([], $offenders, "نصّ عطل رصيد داخل متحكّم:\n".implode("\n", $offenders));
    }

    /**
     * B5 — لا رقم يُلصق باسم مفرد. العربية تصرّف المعدود على ستّ صيغ.
     */
    #[Test]
    public function no_template_glues_a_count_to_a_singular_noun(): void
    {
        $offenders = [];
        $nouns = ['رصيد', 'مشروع', 'تقرير', 'مهمة', 'سؤال', 'أداة'];

        foreach ($this->blades('app') as $path) {
            $body = $this->withoutComments((string) file_get_contents($path));

            foreach ($nouns as $noun) {
                if (preg_match('/\}\}\s*'.preg_quote($noun, '/').'\b/u', $body)) {
                    $offenders[] = $this->relative($path).' → '.$noun;
                }
            }
        }

        $this->assertSame([], $offenders, "رقم ملصوق باسم مفرد:\n".implode("\n", $offenders));
    }

    /**
     * @return array<int, string>
     */
    private function blades(string $under = ''): array
    {
        return $this->files(resource_path('views'.($under !== '' ? '/'.$under : '')), '.blade.php');
    }

    /**
     * @return array<int, string>
     */
    private function phpFiles(string $under): array
    {
        return $this->files(base_path($under), '.php');
    }

    /**
     * @return array<int, string>
     */
    private function files(string $root, string $suffix): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            if (str_ends_with($path, $suffix)) {
                $found[] = $path;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * تعليقات Blade ليست مخرجًا: نصٌّ يشرح عطلًا قديمًا لا يجوز أن يُعدّ
     * ارتكابًا له من جديد.
     */
    private function withoutComments(string $body): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/us', '', $body);
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
