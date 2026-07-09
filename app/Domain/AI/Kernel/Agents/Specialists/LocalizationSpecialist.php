<?php

namespace App\Domain\AI\Kernel\Agents\Specialists;

use App\Contracts\AiGatewayInterface;

/**
 * أخصائي التعريب — التجسيد المحلي لوكيل localization-specialist.
 *
 * قدرة أساسية (core) عابرة: تفحص أي مخرَج عربي وتضمن جودته اللغوية والطباعية
 * وفق دستور الهوية (§10 «لا إيموجي»، §39 العربية جوهر لا إضافة). محلي بالكامل —
 * لا نداء خارجي في التحليل ولا التنظيف — والصقل اللغوي عبر LLM آخر ميل اختياري
 * يتدهور بأمان (يعيد النص كما هو عند الغياب).
 */
class LocalizationSpecialist
{
    /** أنماط الإيموجي الشائعة (ممنوعة في الواجهة حسب الدستور §10/§39.4). */
    private const EMOJI = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2B05}-\x{2B07}\x{FE00}-\x{FE0F}\x{1F1E6}-\x{1F1FF}\x{200D}\x{20E3}]/u';

    /**
     * تحليل جودة نص عربي محلياً: درجة + مشاكل مصنّفة + نسخة منظّفة.
     *
     * @return array{score: int, issues: array<int, array{code: string, label: string, severity: string, count: int}>, clean: string}
     */
    public function analyze(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['score' => 0, 'issues' => [], 'clean' => ''];
        }

        $issues = [];

        $emoji = preg_match_all(self::EMOJI, $text);
        if ($emoji > 0) {
            $issues[] = ['code' => 'emoji', 'label' => 'رموز تعبيرية ممنوعة في الواجهة', 'severity' => 'high', 'count' => $emoji];
        }

        $straightQuotes = preg_match_all('/["\']/u', $text);
        if ($straightQuotes > 0) {
            $issues[] = ['code' => 'quotes', 'label' => 'اقتباسات مستقيمة بدل «…»', 'severity' => 'low', 'count' => $straightQuotes];
        }

        // خلط لاتيني داخل نص عربي الغالب (أسماء/مصطلحات لم تُعرَّب) — تنبيه لا حجب.
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latinRuns = preg_match_all('/[A-Za-z]{3,}/u', $text);
        if ($arabic > 0 && $latinRuns > 0 && $arabic >= $latinRuns) {
            $issues[] = ['code' => 'latin_mix', 'label' => 'كلمات لاتينية داخل نص عربي', 'severity' => 'medium', 'count' => $latinRuns];
        }

        // ترقيم لاتيني حيث يُتوقّع عربي (، ؛ ؟) — قابل للإصلاح تلقائياً.
        $asciiPunct = preg_match_all('/\p{Arabic}\s*[,;?]/u', $text);
        if ($asciiPunct > 0) {
            $issues[] = ['code' => 'punctuation', 'label' => 'ترقيم لاتيني بدل العربي (، ؛ ؟)', 'severity' => 'low', 'count' => $asciiPunct];
        }

        $spacing = preg_match_all('/ {2,}| [،؛؟.!]/u', $text);
        if ($spacing > 0) {
            $issues[] = ['code' => 'spacing', 'label' => 'مسافات زائدة أو قبل الترقيم', 'severity' => 'low', 'count' => $spacing];
        }

        return [
            'score' => $this->score($issues),
            'issues' => $issues,
            'clean' => $this->clean($text),
        ];
    }

    /**
     * تنظيف تحويلي آمن وحتمي: يزيل الإيموجي، يحوّل الاقتباسات والترقيم للعربي،
     * ويضغط المسافات. لا يلمس النص اللاتيني (خطر تشويه أسماء) — يُبلَّغ عنه فقط.
     */
    public function clean(string $text): string
    {
        $text = (string) preg_replace(self::EMOJI, '', $text);

        // اقتباسات مستقيمة → عربية «…» (تُطابَق كزوج).
        $text = (string) preg_replace_callback(
            '/"([^"]*)"/u',
            fn (array $m): string => '«'.trim($m[1]).'»',
            $text,
        );
        $text = str_replace(['"', "'"], ['«', '»'], $text);

        // ترقيم لاتيني بعد حرف عربي → عربي.
        $text = (string) preg_replace('/(\p{Arabic})\s*,/u', '$1،', $text);
        $text = (string) preg_replace('/(\p{Arabic})\s*;/u', '$1؛', $text);
        $text = (string) preg_replace('/(\p{Arabic})\s*\?/u', '$1؟', $text);

        // مسافة قبل الترقيم، ثم ضغط المسافات المكرّرة.
        $text = (string) preg_replace('/\s+([،؛؟.!])/u', '$1', $text);
        $text = (string) preg_replace('/ {2,}/u', ' ', $text);

        return trim($text);
    }

    /** درجة 0-100: تبدأ من 100 وتنقص بوزن حسب شدّة كل مشكلة. */
    private function score(array $issues): int
    {
        $penalty = 0;
        foreach ($issues as $issue) {
            $penalty += match ($issue['severity']) {
                'high' => 25 + min(15, (int) $issue['count'] * 5),
                'medium' => 12 + min(10, (int) $issue['count'] * 2),
                default => 4 + min(6, (int) $issue['count']),
            };
        }

        return max(0, 100 - $penalty);
    }

    /**
     * آخر ميل اختياري: صقل الصياغة العربية عبر LLM. يتدهور بأمان — يعيد النص
     * المنظّف محلياً كما هو عند غياب أي مزوّد أو أي فشل.
     */
    public function polish(string $text): string
    {
        $local = $this->clean($text);

        try {
            $refined = app(AiGatewayInterface::class)->generateText(
                'أعد صياغة النص التالي بعربية فصحى حديثة واضحة ومباشرة، دون إيموجي ودون تغيير المعنى، وأعد النص فقط:'."\n\n".$local,
                'أنت محرّر عربي محترف. تحافظ على المعنى وتحسّن الوضوح والسلاسة. لا تضف إيموجي. أعد النص المحرّر فقط بلا مقدمات.',
            );
        } catch (\Throwable) {
            return $local;
        }

        $refined = trim((string) $refined);

        return $refined !== '' ? $this->clean($refined) : $local;
    }
}
