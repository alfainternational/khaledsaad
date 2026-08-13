<?php

namespace App\Services\Tools\V2;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * بوابة دلالية أخيرة بين مخرج النموذج وحفظ التقرير.
 *
 * المخطط يثبت الشكل، وهذه الطبقة تثبت المعنى: لا رقم بلا أصل، ولا دليل
 * غير موجود في مدخلات العميل، ولا تعليمات محقونة أو توصيات مكررة.
 */
class ReportSemanticGuard
{
    /**
     * @param  array<string, mixed>  $synthesis
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $baseline
     * @return array<string, mixed>
     */
    public function repair(array $synthesis, array $context, array $baseline): array
    {
        unset($synthesis['score'], $synthesis['base_score'], $synthesis['score_band']);

        if (isset($synthesis['confidence'])) {
            $synthesis['confidence'] = max(0, min(100, (int) $synthesis['confidence']));
        }

        $source = $this->searchable([$context, $baseline]);
        $answerReferences = $this->answerReferences($context);
        $allowedNumbers = $this->numbers($source);
        $seen = [];
        $findings = [];
        $assumptions = Arr::wrap($synthesis['assumptions'] ?? []);

        foreach (Arr::wrap($synthesis['findings'] ?? []) as $finding) {
            if (! is_array($finding) || $this->containsInjection($finding)) {
                continue;
            }

            $title = trim((string) ($finding['title'] ?? ''));
            $fingerprint = Str::lower(preg_replace('/\s+/u', ' ', $title) ?? $title);

            if ($title === '' || isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $evidence = trim((string) ($finding['evidence'] ?? ''));
            $explicitReference = trim((string) ($finding['evidence_answer_ref'] ?? ''));
            $supported = ($explicitReference !== '' && array_key_exists($explicitReference, $answerReferences))
                || $this->evidenceSupported($evidence, $source);
            // فحص الأرقام يقتصر على حقول الادعاء (ما يُقدَّم كحقيقة عن النشاط):
            // العنوان والوصف والدليل. أرقام التوصيات أهداف فعل توجيهية مشروعة
            // («انشر 3 مرات»، «استهدف 30 عميلًا») لا ادعاءات، فلا تُسقط الدليل.
            $claim = $this->searchable([
                'title' => $finding['title'] ?? '',
                'description' => $finding['description'] ?? '',
                'evidence' => $finding['evidence'] ?? '',
            ]);
            $unsupportedNumbers = array_diff(
                $this->numbers($claim),
                $allowedNumbers,
            );

            if (! $supported || $unsupportedNumbers !== []) {
                $finding['is_assumption'] = true;
                unset($finding['evidence']);

                if ($unsupportedNumbers !== []) {
                    $assumptions[] = 'تحتاج الأرقام الواردة في نتيجة «'.$title.'» إلى قياس أو مصدر قبل اعتمادها.';
                }
            } else {
                $finding['is_assumption'] = (bool) ($finding['is_assumption'] ?? false);
                $finding['evidence_answer_ref'] = $this->closestAnswerReference($evidence, $answerReferences);
            }

            $finding['claim_type'] = $finding['is_assumption'] ? 'inference' : 'observed';
            $finding['provenance'] = $finding['is_assumption']
                ? 'inference_from_inputs'
                : 'user_input';
            $finding['recommendations'] = collect(Arr::wrap($finding['recommendations'] ?? []))
                ->filter(fn ($recommendation) => is_array($recommendation) && ! $this->containsInjection($recommendation))
                ->unique(fn (array $recommendation) => Str::lower(trim((string) ($recommendation['title'] ?? ''))))
                ->take(3)
                ->values()
                ->all();

            if ($finding['recommendations'] !== []) {
                $findings[] = $finding;
            }
        }

        $synthesis['findings'] = array_slice($findings, 0, 8);
        $synthesis['assumptions'] = array_values(array_slice(array_unique(array_filter(
            array_map(fn ($item) => trim((string) $item), $assumptions),
        )), 0, 10));

        if ($this->containsInjection($synthesis['summary'] ?? '')) {
            unset($synthesis['summary']);
        }

        $firstRecommendation = $synthesis['findings'][0]['recommendations'][0] ?? null;
        if (is_array($firstRecommendation)) {
            $synthesis['next_step'] = [
                'title' => $firstRecommendation['title'],
                'description' => $firstRecommendation['description'],
            ];
        } elseif ($this->containsInjection($synthesis['next_step'] ?? [])) {
            unset($synthesis['next_step']);
        }

        return $synthesis;
    }

    /** @param array<string, mixed> $context @return array<string, string> */
    private function answerReferences(array $context): array
    {
        $answers = data_get($context, 'snapshot.answers', []);

        if (! is_array($answers)) {
            return [];
        }

        $references = [];
        foreach ($answers as $key => $value) {
            $reference = is_array($value) && isset($value['key']) ? (string) $value['key'] : (string) $key;
            $text = $this->searchable($value);
            if ($text !== '') {
                $references[$reference] = $text;
            }
        }

        return $references;
    }

    /** @param array<string, string> $references */
    private function closestAnswerReference(string $evidence, array $references): ?string
    {
        $needle = $this->arabify($this->normalise($evidence));
        $tokens = array_values(array_diff($this->contentTokens($needle), self::STOPWORDS));
        $best = null;
        $score = 0.0;

        foreach ($references as $key => $value) {
            $candidate = array_values(array_diff($this->contentTokens($this->arabify($value)), self::STOPWORDS));
            $union = array_unique([...$tokens, ...$candidate]);
            $current = $union === [] ? 0.0 : count(array_intersect($tokens, $candidate)) / count($union);
            if ($current > $score) {
                $score = $current;
                $best = $key;
            }
        }

        return $best;
    }

    /**
     * كلمات وظيفية لا تحمل مضمونًا — لا تُحسب في تغطية الدليل.
     * مطبَّعة مسبقًا بنفس تطبيع المقارنة (بلا أل التعريف ولا همزات).
     */
    private const STOPWORDS = [
        'من', 'في', 'علي', 'عن', 'الي', 'ان', 'كان', 'كانت', 'مع', 'هذا', 'هذه',
        'ذلك', 'تلك', 'التي', 'الذي', 'ما', 'لا', 'لم', 'لن', 'هو', 'هي', 'هم',
        'انت', 'انا', 'نحن', 'ثم', 'حتي', 'قد', 'لقد', 'كل', 'بعض', 'غير', 'بين',
        'عند', 'او', 'اذا', 'لكن', 'كما', 'فيه', 'فيها', 'بها', 'به', 'له', 'لها',
        'لديه', 'لديك', 'عنده', 'عندك', 'اجاب', 'قال', 'ذكر', 'كتب',
        // أفعال الإسناد التي يفتتح بها النموذج اقتباسه — تأطير لا مضمون.
        'ذكرت', 'قلت', 'اجبت', 'كتبت', 'وصفت', 'اخبرتنا', 'اشرت',
    ];

    /**
     * الدليل مدعوم إن ظهر حرفيًا في المصدر، أو إن كانت جُلّ كلماته الدالة
     * موجودة فيه بعد تطبيع عربي — فإعادة الصياغة الأمينة («المقهى» بدل
     * «مقهى»، همزة مختلفة، تقديم وتأخير) لا تُسقط نتيجة صادقة، بينما
     * الدليل المختلَق تبقى كلماته غائبة عن المصدر فيسقط كما كان.
     */
    private function evidenceSupported(string $evidence, string $source): bool
    {
        if ($evidence === '') {
            return false;
        }

        if (Str::contains($source, $this->normalise($evidence))) {
            return true;
        }

        $needle = $this->arabify($this->normalise($evidence));
        $haystack = $this->arabify($source);

        if ($needle !== '' && Str::contains($haystack, $needle)) {
            return true;
        }

        $tokens = array_values(array_diff($this->contentTokens($needle), self::STOPWORDS));

        // أقل من كلمتين دالتين لا تكفي حكمًا — نبقى على السلوك المحافظ.
        if (count($tokens) < 2) {
            return false;
        }

        $sourceTokens = $this->contentTokens($haystack);
        $sourceSet = array_flip($sourceTokens);
        $found = count(array_filter(
            $tokens,
            fn (string $token) => isset($sourceSet[$token]) || $this->tokenNearlyMatches($token, $sourceTokens),
        ));

        return $found / count($tokens) >= 0.7;
    }

    /**
     * تسامح لاحقات خفيف: «زوار» و«زوارا» كلمة واحدة — تطابق بادئة مشتركة
     * لا تقل عن أربعة أحرف يكفي، دون أي اجتهاد أعمق من ذلك.
     */
    private function tokenNearlyMatches(string $token, array $sourceTokens): bool
    {
        if (mb_strlen($token) < 4) {
            return false;
        }

        foreach ($sourceTokens as $candidate) {
            if (mb_strlen($candidate) < 4) {
                continue;
            }

            if (str_starts_with($token, $candidate) || str_starts_with($candidate, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * تطبيع عربي للمقارنة فقط: إسقاط التشكيل والتطويل، توحيد الهمزات
     * والألف المقصورة والتاء المربوطة، ونزع أل التعريف من أول الكلمات.
     */
    private function arabify(string $value): string
    {
        $value = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $value);
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي']);

        // «والمبيعات» = واو عطف + أل تعريف + الكلمة: يُنزعان معًا في أول
        // الكلمة فقط، حيث لا لبس مع كلمات تبدأ بواو أصلية مثل «وصف».
        $value = (string) preg_replace('/(?<![\pL\pN])[وفب]?ال(?=\pL\pL)/u', '', $value);

        return $value;
    }

    /** @return array<int, string> */
    private function contentTokens(string $value): array
    {
        preg_match_all('/[\pL\pN]{2,}/u', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function searchable(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->normalise(is_string($encoded) ? $encoded : '');
    }

    private function normalise(string $value): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    /** @return array<int, string> */
    private function numbers(string $value): array
    {
        preg_match_all('/(?<![\pL\pN])\d+(?:[.,]\d+)?/u', $value, $matches);

        return array_values(array_unique(array_map(
            fn (string $number) => str_replace(',', '.', ltrim($number, '0') ?: '0'),
            $matches[0] ?? [],
        )));
    }

    private function containsInjection(mixed $value): bool
    {
        $text = $this->searchable($value);

        foreach ([
            'ignore previous', 'ignore all instructions', 'system prompt',
            'تجاهل التعليمات', 'تجاهل كل ما سبق', 'تعليمات النظام',
            'اكشف البرومبت', 'اطبع البرومبت',
        ] as $needle) {
            if (Str::contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
