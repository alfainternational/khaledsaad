<?php

namespace App\Modules\Diagnosis;

/**
 * تناقض ما قاله صاحب النشاط عن نفسه، مرصودًا بلا نموذج لغوي.
 *
 * **الثغرة التي يسدّها:** `BrainWriter` يعلّم التعارض حين يختلف **المصدر**
 * (§٩)، وهو قرار صحيح: تصحيح المستخدم لنفسه ليس تعارضًا. لكنّ حقلين
 * مختلفين يصفان الشيء نفسه بكلامين متباعدين لا يمرّان بتلك القاعدة أصلًا —
 * فمن كتب جمهوره «أمهات في الرياض» في سؤال و«شركات متوسطة» في سؤال آخر
 * كان يمرّ بلا ملاحظة، ثم تُبنى له رسالة تخاطب جمهورين.
 *
 * والمصدر الوحيد لهذا الرصد كان مرحلة `gaps` — أي استعلام نموذج واحد. وهو
 * يخالف §٤.٢: قياسٌ من عيّنة واحدة. هذا الفحص حتميّ ومحليّ، يعطي النتيجة
 * نفسها في كل مرة، ولا يستهلك من ميزانية المساحة شيئًا.
 *
 * `Diagnosis` لا يتصل بالإنترنت (§٨): الحساب هنا رمزيّ صرف على نصّ محفوظ.
 */
final class ConsistencyInspector
{
    /**
     * أدنى تداخل رمزي بين وصفين لنفس المعنى قبل عدّهما متناقضين.
     *
     * الرقم منخفض عمدًا. الهدف رصد التباعد الصريح — «أمهات» مقابل «شركات» —
     * لا اختلاف الصياغة. وعتبةٌ أعلى تحوّل كل إعادة صياغة إلى إنذار، فتُهمَل
     * القائمة كلها بعد ثلاثة إنذارات كاذبة.
     */
    private const OVERLAP_THRESHOLD = 0.16;

    /**
     * أدنى عدد كلمات دالّة في الطرفين قبل إصدار حكم.
     *
     * «الجميع» و«الكل» جوابان قصيران متباعدان رمزيًّا، والحكم عليهما بالتناقض
     * خطأ: مشكلتهما ضعف الكفاية لا التناقض، ويقيسها `input_fitness`.
     */
    private const MIN_TOKENS = 3;

    /**
     * أزواج الحقول التي يجب أن تتفق، ووصف ما يعنيه اختلافها.
     *
     * تُعلَن هنا بيانًا لا تُبثّ في الكود: الزوج الذي يُضاف لاحقًا يُكتب سطرًا
     * واحدًا، ولا يحتاج من يضيفه أن يقرأ منطق المقارنة.
     *
     * @var array<int, array{left: string, right: string, subject: string}>
     */
    private const PAIRS = [
        ['left' => 'audience', 'right' => 'best_customer', 'subject' => 'جمهورك'],
        ['left' => 'audience', 'right' => 'target_customer_guess', 'subject' => 'جمهورك'],
        ['left' => 'best_customer', 'right' => 'decision_audience', 'subject' => 'من يقرر الشراء'],
        ['left' => 'value_proposition', 'right' => 'differentiator', 'subject' => 'سبب الشراء منك'],
        ['left' => 'what_you_sell', 'right' => 'description', 'subject' => 'ما تبيعه'],
        ['left' => 'customer_problem', 'right' => 'objection', 'subject' => 'ما يوقف عميلك'],
    ];

    /**
     * @param  array<string, mixed>  $answers  إجابات المشروع بمفاتيحها
     * @return array<int, array{subject: string, left_key: string, right_key: string, left: string, right: string}>
     */
    public function inspect(array $answers): array
    {
        $found = [];

        foreach (self::PAIRS as $pair) {
            $left = $this->text($answers[$pair['left']] ?? null);
            $right = $this->text($answers[$pair['right']] ?? null);

            if ($left === '' || $right === '') {
                continue;
            }

            $leftTokens = $this->tokens($left);
            $rightTokens = $this->tokens($right);

            if (count($leftTokens) < self::MIN_TOKENS || count($rightTokens) < self::MIN_TOKENS) {
                continue;
            }

            if ($this->overlap($leftTokens, $rightTokens) >= self::OVERLAP_THRESHOLD) {
                continue;
            }

            $found[] = [
                'subject' => $pair['subject'],
                'left_key' => $pair['left'],
                'right_key' => $pair['right'],
                'left' => $left,
                'right' => $right,
            ];
        }

        return $found;
    }

    private function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['value'] ?? implode(' ', array_filter($value, 'is_scalar'));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function overlap(array $left, array $right): float
    {
        $union = count(array_unique(array_merge($left, $right)));

        return $union === 0 ? 1.0 : count(array_intersect($left, $right)) / $union;
    }

    /**
     * الكلمات الدالّة وحدها: بلا تطويل ولا ترقيم ولا حروف ربط.
     *
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        $text = preg_replace('/\x{0640}/u', '', $text) ?? $text;
        $text = preg_replace('/[\p{P}\p{S}]+/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        $stop = ['في', 'من', 'على', 'الى', 'إلى', 'و', 'أو', 'مع', 'عن', 'الذي', 'التي',
            'هذا', 'هذه', 'ما', 'هو', 'هي', 'ثم', 'كل', 'بين', 'أن', 'قد', 'التي', 'عند'];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, $stop, true),
        )));
    }
}
