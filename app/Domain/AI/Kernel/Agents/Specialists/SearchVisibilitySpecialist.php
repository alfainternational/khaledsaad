<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

/**
 * أخصائي الظهور في البحث — التجسيد المحلي لوكيل seo-specialist.
 *
 * يفحص محتوى (عنوان + متن + كلمة مستهدفة) محلياً وفق مبادئ SEO التقليدية
 * ومحرّكات الإجابة (AEO) والتوليد (GEO): وجود الكلمة في العنوان والفقرة الأولى،
 * طول العنوان، البنية المُجيبة أولاً (answer-first)، ووجود أسئلة تلتقطها المقتطفات.
 * لا نداء خارجي — تحليل حتمي.
 *
 * السطح: يُحقن في أدوات المرحلة 2 (السوق) والمحتوى.
 */
class SearchVisibilitySpecialist
{
    /**
     * @return array{score: int, findings: array<int, array{code: string, label: string, severity: string, type: string, hint: string}>}
     */
    public function analyze(string $title, string $body, string $keyword = ''): array
    {
        $title = trim($title);
        $body = trim($body);
        $keyword = trim($keyword);

        $findings = [];
        $score = 100;

        // العنوان: الطول العملي (عربي ~ 30-60 محرفاً).
        $titleLen = mb_strlen($title);
        if ($title === '') {
            $findings[] = $this->f('no_title', 'لا يوجد عنوان', 'high', 'SEO', 'أضف عنواناً واضحاً يحوي الكلمة المستهدفة.');
            $score -= 25;
        } elseif ($titleLen < 20 || $titleLen > 65) {
            $findings[] = $this->f('title_length', 'طول العنوان خارج النطاق المثالي', 'low', 'SEO', 'اجعل العنوان بين 20 و65 محرفاً ('.$titleLen.' حالياً).');
            $score -= 8;
        }

        if ($keyword !== '') {
            // الكلمة في العنوان.
            if ($title !== '' && mb_stripos($title, $keyword) === false) {
                $findings[] = $this->f('keyword_title', 'الكلمة المستهدفة غائبة عن العنوان', 'high', 'SEO', 'أدرج «'.$keyword.'» في العنوان.');
                $score -= 20;
            }

            // الكلمة في الفقرة الأولى (أول 160 محرفاً).
            $intro = mb_substr($body, 0, 160);
            if ($body !== '' && mb_stripos($intro, $keyword) === false) {
                $findings[] = $this->f('keyword_intro', 'الكلمة غائبة عن المقدّمة', 'medium', 'SEO', 'اذكر «'.$keyword.'» في أول سطرين.');
                $score -= 12;
            }

            // الكثافة: تكرار مفرط.
            $occurrences = mb_substr_count(mb_strtolower($body), mb_strtolower($keyword));
            $wordCount = max(1, count(array_filter(preg_split('/\s+/u', $body) ?: [])));
            $density = $occurrences / $wordCount;
            if ($density > 0.04) {
                $findings[] = $this->f('keyword_stuffing', 'حشو الكلمة المفتاحية', 'medium', 'SEO', 'قلّل تكرار «'.$keyword.'»؛ الكثافة مرتفعة.');
                $score -= 10;
            }
        } else {
            $findings[] = $this->f('no_keyword', 'لا كلمة مستهدفة محدّدة', 'low', 'SEO', 'حدّد كلمة/عبارة يبحث بها جمهورك.');
            $score -= 5;
        }

        // AEO: بنية مُجيبة أولاً — يبدأ المتن بجواب مباشر لا بمقدّمة عامة.
        if ($body !== '' && ! $this->startsWithDirectAnswer($body)) {
            $findings[] = $this->f('answer_first', 'المحتوى لا يبدأ بجواب مباشر', 'medium', 'AEO', 'ابدأ بجملة تجيب السؤال مباشرة لتلتقطها محرّكات الإجابة.');
            $score -= 10;
        }

        // AEO/GEO: وجود سؤال صريح يلتقطه مقتطف/محرّك توليد.
        if ($body !== '' && mb_strpos($body, '؟') === false && mb_strpos($body, '?') === false) {
            $findings[] = $this->f('no_question', 'لا أسئلة صريحة في المحتوى', 'low', 'GEO', 'أضف سؤالاً بصيغة جمهورك مع إجابته لتحسين الظهور في الإجابات المولّدة.');
            $score -= 6;
        }

        return ['score' => max(0, min(100, $score)), 'findings' => $findings];
    }

    /** يبدأ المتن بجواب مباشر (لا بأداة استفهام/مقدّمة مطوّلة). */
    private function startsWithDirectAnswer(string $body): bool
    {
        $first = mb_substr(ltrim($body), 0, 60);
        // مؤشّر ضعيف لكن عملي: أول جملة قصيرة تنتهي سريعاً، ولا تبدأ بـ«في هذا المقال».
        if (mb_stripos($first, 'في هذا المقال') !== false || mb_stripos($first, 'سنتحدث') !== false) {
            return false;
        }

        return true;
    }

    /**
     * @return array{code: string, label: string, severity: string, type: string, hint: string}
     */
    private function f(string $code, string $label, string $severity, string $type, string $hint): array
    {
        return ['code' => $code, 'label' => $label, 'severity' => $severity, 'type' => $type, 'hint' => $hint];
    }
}
