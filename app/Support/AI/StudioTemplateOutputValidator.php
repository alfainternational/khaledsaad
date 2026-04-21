<?php

namespace App\Support\AI;

use App\Domain\AI\Models\AITemplate;

class StudioTemplateOutputValidator
{
    public function __construct(
        private readonly StudioTemplateContractRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>|null  $contract
     * @return list<string>
     */
    public function issuesFor(string $output, ?array $contract = null, ?AITemplate $template = null): array
    {
        $issues = [];
        $text = trim(str_replace("\r\n", "\n", $output));

        if ($text === '') {
            return ['المخرج فارغ تماماً.'];
        }

        $definition = $this->registry->definitionFor($template);
        $wordCount = $this->wordCount($text);
        $sectionCount = preg_match_all('/^##\s+.+$/mu', $text) ?: 0;
        $genericPhraseHits = $this->genericPhraseHits($text);

        if ($wordCount < 220) {
            $issues[] = 'المخرج قصير أكثر من اللازم ولا يبدو كملف تسليم مكتمل.';
        }

        if ($sectionCount < 3) {
            $issues[] = 'المخرج لا يحتوي على عدد كافٍ من الأقسام الرئيسية بعناوين Markdown واضحة.';
        }

        if ($genericPhraseHits >= 4) {
            $issues[] = 'المخرج يستخدم لغة عامة أو مستقبلية مثل "سوف" و"سيتم" بدل نصوص تنفيذية جاهزة.';
        }

        if (! preg_match('/^##\s+المنف[ّ]?ذون\s+المستهدفون/mu', $text)) {
            $issues[] = 'قسم المنفّذين المستهدفين غير موجود أو غير واضح.';
        }

        if (preg_match('/\.\.\.|TODO|TBD/iu', $text)) {
            $issues[] = 'المخرج يحتوي على placeholders أو فراغات مثل "..." بدل مادة جاهزة للتسليم.';
        }

        $contractCoverage = $this->contractCoverage($text, $contract);
        if ($contractCoverage !== null && $contractCoverage < 0.5) {
            $issues[] = 'المخرج لا يغطي عدداً كافياً من الأقسام الإلزامية المتوقعة من القالب.';
        }

        foreach ($definition['required_fragments'] ?? [] as $fragment) {
            if (! $this->containsNormalized($text, (string) $fragment)) {
                $issues[] = 'المخرج يفتقد عنصراً إلزامياً لهذا القالب: '.$fragment.'.';
            }
        }

        foreach ($definition['forbidden_fragments'] ?? [] as $fragment) {
            if ($this->containsNormalized($text, (string) $fragment)) {
                $issues[] = 'المخرج تسرّبت إليه مادة تخص قالباً أو مخرجاً آخر: '.$fragment.'.';
            }
        }

        foreach ($definition['generic_red_flags'] ?? [] as $fragment) {
            if ($this->containsNormalized($text, (string) $fragment)) {
                $issues[] = 'المخرج ما زال يعتمد تعبيراً Commodity أو غير مقنع لهذا القالب: '.$fragment.'.';
            }
        }

        $issues = array_merge($issues, $this->templateSpecificIssues($text, $template));

        return array_values(array_unique($issues));
    }

    private function wordCount(string $text): int
    {
        $tokens = preg_split('/\s+/u', preg_replace('/\s+/u', ' ', trim($text)) ?: '');

        return count(array_filter($tokens, fn (?string $token): bool => $token !== null && $token !== ''));
    }

    private function genericPhraseHits(string $text): int
    {
        $patterns = [
            '/\bسوف\b/u',
            '/سيتم/u',
            '/ينبغي/u',
            '/يمكن\s+التركيز/u',
            '/ما\s+يجب/u',
            '/من\s+المهم/u',
            '/يُفضّل/u',
            '/يفضل/u',
            '/سيعمل/u',
            '/سيقوم/u',
        ];

        $hits = 0;
        foreach ($patterns as $pattern) {
            $hits += preg_match_all($pattern, $text) ?: 0;
        }

        return $hits;
    }

    /**
     * @param  array<string, mixed>|null  $contract
     */
    private function contractCoverage(string $text, ?array $contract): ?float
    {
        $sections = collect($contract['sections'] ?? [])
            ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
            ->map(fn (mixed $title): string => $this->normalizeHeading((string) $title))
            ->filter()
            ->values();

        if ($sections->isEmpty()) {
            return null;
        }

        $normalizedOutput = $this->normalizeHeading($text);
        $matched = $sections->filter(function (string $section) use ($normalizedOutput): bool {
            return mb_strpos($normalizedOutput, $section) !== false;
        })->count();

        return $matched / max($sections->count(), 1);
    }

    private function normalizeHeading(string $text): string
    {
        $text = preg_replace('/[(){}\[\]:"“”«»\-\|\.,؛،]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return mb_strtolower($text);
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        return mb_strpos($this->normalizeHeading($haystack), $this->normalizeHeading($needle)) !== false;
    }

    /**
     * @return list<string>
     */
    private function templateSpecificIssues(string $text, ?AITemplate $template): array
    {
        $code = (string) ($template?->code ?? '');

        return match ($code) {
            'social-ad' => $this->socialAdIssues($text),
            'landing-headlines' => $this->landingIssues($text),
            'whatsapp-followup' => $this->whatsappIssues($text),
            'email-sequence' => $this->emailIssues($text),
            'content-calendar' => $this->contentCalendarIssues($text),
            'sales-script' => $this->salesScriptIssues($text),
            'brand-diagnosis' => $this->brandDiagnosisIssues($text),
            'brand-positioning' => $this->brandPositioningIssues($text),
            'brand-voice-guide' => $this->brandVoiceIssues($text),
            'brand-full-pack' => $this->brandPackIssues($text),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private function socialAdIssues(string $text): array
    {
        $issues = [];
        $variantCount = preg_match_all('/(?:###\s*النسخة|النسخة\s+(?:الأولى|الثانية|الثالثة|الرابعة)|Primary text|النص الأساسي)/u', $text) ?: 0;
        if ($variantCount < 4) {
            $issues[] = 'قالب الإعلان لا يحتوي على عدد كافٍ من النسخ الإعلانية الكاملة.';
        }

        if (! preg_match('/زاوية الاختبار/u', $text) || ! preg_match('/الاعتراض الذي تعالجه/u', $text)) {
            $issues[] = 'قالب الإعلان لا يوضح زاوية الاختبار أو الاعتراض الذي تعالجه كل نسخة.';
        }

        if (! preg_match('/مصفوفة الاختبار/u', $text)) {
            $issues[] = 'قالب الإعلان يفتقد مصفوفة اختبار توضّح ما يبدأ أولاً وما يتغير عند ضعف الأداء.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function landingIssues(string $text): array
    {
        $issues = [];

        if (! preg_match('/FAQ|الأسئلة الشائعة/u', $text)) {
            $issues[] = 'قالب صفحة الهبوط يفتقد قسم FAQ أو ما يعادله.';
        }

        if (! preg_match('/H1|العنوان الرئيسي/u', $text) || ! preg_match('/H2|العنوان الفرعي/u', $text)) {
            $issues[] = 'قالب صفحة الهبوط لا يقدّم H1 وH2 بصيغة صريحة قابلة للصق.';
        }

        if (! preg_match('/وعد الصفحة/u', $text) || ! preg_match('/الاعتراض الأكبر/u', $text)) {
            $issues[] = 'قالب صفحة الهبوط لا يحدد وعد الصفحة أو الاعتراض الأكبر الذي يجب كسره.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function whatsappIssues(string $text): array
    {
        $issues = [];

        if (preg_match('/عنوان\s*(?:للرسالة|الرسالة)/u', $text)) {
            $issues[] = 'قالب واتساب يحتوي على عنوان رسالة رغم أن ذلك ممنوع في هذا النوع.';
        }

        $messageCount = preg_match_all('/رسالة\s+\d+/u', $text) ?: 0;
        if ($messageCount < 5) {
            $issues[] = 'قالب واتساب لا يحتوي على عدد كافٍ من الرسائل الجاهزة.';
        }

        if (! preg_match('/شرط التفعيل/u', $text) || ! preg_match('/قواعد القرار/u', $text)) {
            $issues[] = 'قالب واتساب يفتقد شرط التفعيل أو قواعد القرار الخاصة بالمتابعة والتوقف.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function emailIssues(string $text): array
    {
        $issues = [];
        $mailCount = preg_match_all('/الإيميل\s+\d+/u', $text) ?: 0;
        if ($mailCount < 3) {
            $issues[] = 'قالب الإيميل لا يحتوي على ثلاثة إيميلات واضحة ومنفصلة.';
        }

        if (! preg_match('/السلوك المتوقع/u', $text) || ! preg_match('/الاعتراض الذي يعالجه/u', $text)) {
            $issues[] = 'قالب الإيميل لا يوضح السلوك المتوقع أو الاعتراض الذي يعالجه كل إيميل.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function contentCalendarIssues(string $text): array
    {
        $issues = [];
        if (! preg_match('/اليوم|الأحد|الاثنين|الثلاثاء|الأربعاء|الخميس|الجمعة|السبت/u', $text)) {
            $issues[] = 'خطة المحتوى لا تبدو كتقويم يومي حقيقي يمكن تنفيذه على مدار الأسبوع.';
        }

        if (! preg_match('/الفكرة المركزية/u', $text) || ! preg_match('/أعمدة المحتوى/u', $text)) {
            $issues[] = 'خطة المحتوى تفتقد الفكرة المركزية للأسبوع أو أعمدة المحتوى التي تنظّم الأيام.';
        }

        if (! preg_match('/خطة المضاعفة/u', $text)) {
            $issues[] = 'خطة المحتوى تفتقد منطق المضاعفة وإعادة استخدام المحتوى الناجح.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function salesScriptIssues(string $text): array
    {
        $issues = [];
        if (! preg_match('/المندوب\s*:|العميل\s*:/u', $text)) {
            $issues[] = 'سكربت البيع لا يحتوي على حوار فعلي بين المندوب والعميل.';
        }

        if ((preg_match_all('/اعتراض/u', $text) ?: 0) < 4) {
            $issues[] = 'سكربت البيع يفتقد عدداً كافياً من الاعتراضات والردود الواقعية.';
        }

        if (! preg_match('/أسئلة التأهيل/u', $text) || ! preg_match('/فروع القرار/u', $text)) {
            $issues[] = 'سكربت البيع يفتقد أسئلة التأهيل أو فروع القرار لما بعد المكالمة.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function brandDiagnosisIssues(string $text): array
    {
        $issues = [];
        if (! preg_match('/90\s*يوماً|90 يوما/u', $text)) {
            $issues[] = 'تشخيص البراند لا يحتوي على أفق 90 يوماً كما يقتضي القالب.';
        }

        if (! preg_match('/خريطة الجذور/u', $text) || ! preg_match('/أولوية الإصلاح الأولى/u', $text)) {
            $issues[] = 'تشخيص البراند يفتقد خريطة الجذور أو أولوية الإصلاح الأولى.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function brandPositioningIssues(string $text): array
    {
        $issues = [];

        if (! preg_match('/Segment/u', $text) || ! preg_match('/Moment/u', $text) || ! preg_match('/Unique Mechanism/u', $text)) {
            $issues[] = 'بيان التموضع لا يوضح Segment وMoment وUnique Mechanism بشكل صريح.';
        }

        if (! preg_match('/Positioning الداخلي/u', $text) || ! preg_match('/Elevator pitch/u', $text) || ! preg_match('/نسخة قصيرة للموقع/u', $text) || ! preg_match('/رسالة بيع افتتاحية/u', $text)) {
            $issues[] = 'بيان التموضع لا يفصل بوضوح بين التموضع الداخلي وElevator pitch ونسخة الموقع ورسالة البيع.';
        }

        $messageAssets = [
            '/Positioning الداخلي\s*[:\-]/u',
            '/Elevator pitch\s*[:\-]/u',
            '/نسخة قصيرة للموقع\s*[:\-]/u',
            '/رسالة بيع افتتاحية\s*[:\-]/u',
        ];
        $structuredAssets = collect($messageAssets)->filter(fn (string $pattern): bool => preg_match($pattern, $text) === 1)->count();
        if ($structuredAssets < 3) {
            $issues[] = 'بيان التموضع لا يقدّم أصول الرسائل بصيغة مستقلة قابلة للاقتطاع والاستخدام المباشر.';
        }

        if (! preg_match('/لمن لا نخدم/u', $text) || ! preg_match('/أسباب الثقة/u', $text) || ! preg_match('/Value Proposition/u', $text)) {
            $issues[] = 'بيان التموضع يفتقد بعض أصول التميّز الأساسية مثل Value Proposition أو من لا نخدمه أو أسباب الثقة.';
        }

        if (! preg_match('/Framework/u', $text)) {
            $issues[] = 'بيان التموضع يفتقد Framework يشرح كيف يعمل البراند أو الفريق عملياً.';
        }

        if (preg_match('/المشاريع الصغيرة(?:\s|$)|الجميع|كل\s+العملاء/u', $text) && ! preg_match('/أول\s+\d+\s*يو(?:م|ماً)|مرحلة|إطلاق|عيادات|مطاعم|متاجر|استشاري/u', $text)) {
            $issues[] = 'بيان التموضع ما زال عاماً في تعريف الشريحة ويستخدم أوصافاً مثل "المشاريع الصغيرة" دون تضييق حقيقي.';
        }

        $assetValues = collect([
            $this->extractAssetValue($text, 'Positioning الداخلي'),
            $this->extractAssetValue($text, 'Elevator pitch'),
            $this->extractAssetValue($text, 'نسخة قصيرة للموقع'),
            $this->extractAssetValue($text, 'رسالة بيع افتتاحية'),
        ])->filter(fn (?string $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeForComparison($value))
            ->values();

        if ($assetValues->count() >= 2 && $assetValues->unique()->count() < $assetValues->count()) {
            $issues[] = 'بيان التموضع يعيد نفس الرسالة تقريباً بين الأصول المختلفة بدلاً من تفريق الوظائف الاستراتيجية لكل أصل.';
        }

        if (! preg_match('/(?:لا\s|لسنا|لا نقدم|لا نعمل)/u', $text) && preg_match('/ما لا نكونه|لمن لا نخدم/u', $text)) {
            $issues[] = 'قسم الحدود أو من لا نخدمه لا يستخدم صياغة نفي واضحة تحدد ما نستبعده فعلاً.';
        }

        if (preg_match('/تحليل[\s\S]*تنفيذ[\s\S]*تحسين/u', $text) && ! preg_match('/مرحلة|خطوة|ناتج|مخرج/u', $text)) {
            $issues[] = 'Framework التموضع ما زال عاماً جداً ويبدو كعناوين مجردة مثل تحليل/تنفيذ/تحسين بدون مخرجات تشغيلية.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function brandVoiceIssues(string $text): array
    {
        $issues = [];
        if (! preg_match('/Do|Don\'t|Don’t/u', $text) && ! preg_match('/قبل\/بعد|قبل وبعد/u', $text)) {
            $issues[] = 'دليل الصوت لا يحتوي على أمثلة قبل/بعد أو Do/Don’t بشكل صريح.';
        }

        if (! preg_match('/ما الذي لا نقوله أبداً/u', $text) || ! preg_match('/CTA مقبولة/u', $text) || ! preg_match('/CTA مرفوضة/u', $text)) {
            $issues[] = 'دليل الصوت يفتقد المحظورات اللفظية أو أمثلة CTA المقبولة والمرفوضة.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function brandPackIssues(string $text): array
    {
        $issues = [];
        if (! preg_match('/30|60|90/u', $text)) {
            $issues[] = 'الحزمة المتكاملة لا تحتوي على خطة 30-60-90 واضحة.';
        }

        if (! preg_match('/Segment/u', $text) || ! preg_match('/Moment/u', $text) || ! preg_match('/Unique Mechanism/u', $text)) {
            $issues[] = 'الحزمة المتكاملة تفتقد تموضعاً واضحاً مبنياً على Segment وMoment وUnique Mechanism.';
        }

        if (! preg_match('/Framework/u', $text) || ! preg_match('/Boundary/u', $text)) {
            $issues[] = 'الحزمة المتكاملة تفتقد Framework العمل أو Boundary البراند بشكل صريح.';
        }

        return $issues;
    }

    private function extractAssetValue(string $text, string $label): ?string
    {
        $pattern = '/'.preg_quote($label, '/').'\s*[:\-]\s*(.+)$/mu';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function normalizeForComparison(string $text): string
    {
        $text = $this->normalizeHeading($text);
        $text = preg_replace('/\b(?:نحن|نساعد|على|في|من|إلى|هذا|هذه|الذي|التي)\b/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return $text;
    }
}
