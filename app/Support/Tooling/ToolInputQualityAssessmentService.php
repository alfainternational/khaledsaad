<?php

namespace App\Support\Tooling;

use App\Support\AI\WorkspaceGenerationContextBuilder;
use Illuminate\Support\Collection;

class ToolInputQualityAssessmentService
{
    public function __construct(
        private readonly ToolBlueprintCatalog $blueprints,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function assess(
        string $toolCode,
        string $toolName,
        array $inputs,
        ?string $mode = null,
        ?int $workspaceId = null,
        ?int $projectId = null,
    ): array {
        $blueprint = $this->blueprints->for($toolCode);
        $context = $this->contextBuilder->buildForIds($workspaceId, $projectId);
        $fields = $this->resolveFields($blueprint, $mode, $inputs);
        $fieldAssessments = $this->assessFields($fields, $inputs, $context);

        $completeness = $this->completenessScore($fields, $inputs);
        $value = $this->valueScore($fieldAssessments);
        $coherence = $this->coherenceScore($inputs, $fieldAssessments);
        $contextAlignment = $this->contextAlignmentScore($inputs, $context, $fieldAssessments);
        $overall = (int) round(($completeness * 0.25) + ($value * 0.35) + ($coherence * 0.2) + ($contextAlignment * 0.2));

        return [
            'score' => max(0, min(100, $overall)),
            'verdict' => $this->verdictFor($overall, $completeness, $value, $coherence, $contextAlignment),
            'dimensions' => [
                [
                    'key' => 'completeness',
                    'label' => 'الاكتمال',
                    'score' => $completeness,
                    'note' => 'هل الحقول الأساسية مكتملة بما يكفي للانتقال للخطوة التالية؟',
                ],
                [
                    'key' => 'value',
                    'label' => 'قيمة المدخلات',
                    'score' => $value,
                    'note' => 'هل الإجابات محددة وقابلة للبناء عليها أم ما زالت عامة؟',
                ],
                [
                    'key' => 'coherence',
                    'label' => 'المنطق والترابط',
                    'score' => $coherence,
                    'note' => 'هل الحقول تدعم بعضها أم تعيد نفس الفكرة أو تتناقض؟',
                ],
                [
                    'key' => 'context_alignment',
                    'label' => 'الانسجام مع السياق',
                    'score' => $contextAlignment,
                    'note' => 'هل الإجابات منسجمة مع بيانات المشروع والأدوات السابقة؟',
                ],
            ],
            'strengths' => $this->strengths($fieldAssessments, $completeness, $contextAlignment),
            'gaps' => $this->gaps($fieldAssessments, $completeness, $value, $coherence, $contextAlignment),
            'recommendations' => $this->recommendations($fieldAssessments, $fields, $inputs, $context),
            'strategic_note' => $this->strategicNote($toolName, $value, $contextAlignment, $context),
            'field_notes' => $fieldAssessments
                ->mapWithKeys(fn (array $assessment, string $key): array => [$key => $assessment['note']])
                ->all(),
            'field_scores' => $fieldAssessments
                ->mapWithKeys(fn (array $assessment, string $key): array => [$key => [
                    'label' => $assessment['label'],
                    'score' => $assessment['score'],
                    'status' => $assessment['status'],
                ]])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $inputs
     * @return Collection<string, array<string, mixed>>
     */
    private function resolveFields(array $blueprint, ?string $mode, array $inputs): Collection
    {
        $modes = collect($blueprint['modes'] ?? []);

        if ($mode && isset($blueprint['modes'][$mode]['fields'])) {
            return collect($blueprint['modes'][$mode]['fields'])->keyBy('key');
        }

        $inputKeys = collect($inputs)->keys()->filter(fn (mixed $key): bool => $key !== 'brief')->all();
        $matchingFields = $modes
            ->flatMap(fn (array $modeBlueprint): array => $modeBlueprint['fields'] ?? [])
            ->filter(fn (array $field): bool => in_array($field['key'] ?? null, $inputKeys, true))
            ->keyBy('key');

        if ($matchingFields->isNotEmpty()) {
            return $matchingFields;
        }

        return collect($blueprint['modes']['guided']['fields'] ?? [])->keyBy('key');
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $context
     * @return Collection<string, array{label: string, score: int, status: string, note: string}>
     */
    private function assessFields(Collection $fields, array $inputs, array $context): Collection
    {
        return $fields->mapWithKeys(function (array $field, string $key) use ($inputs, $context): array {
            $value = trim((string) ($inputs[$key] ?? ''));
            $label = (string) ($field['label'] ?? $key);
            $tip = trim((string) ($field['answer_tip'] ?? 'أجب بمعلومة محددة يمكن البناء عليها.'));

            if ($value === '') {
                return [$key => [
                    'label' => $label,
                    'score' => 0,
                    'status' => 'empty',
                    'note' => 'الحقل ما زال فارغاً. '.$tip,
                ]];
            }

            $score = 35;
            $wordCount = $this->wordCount($value);
            $genericHits = $this->genericPhraseHits($value);

            if (mb_strlen($value) >= 18) {
                $score += 10;
            }

            if (mb_strlen($value) >= 40) {
                $score += 10;
            }

            if ($wordCount >= 4) {
                $score += 10;
            }

            if (preg_match('/\d|خلال|أول|أسبوع|شهر|يوم|مبيعات|طلبات|عملاء|حجوزات/u', $value)) {
                $score += 10;
            }

            if ($genericHits > 0) {
                $score -= min(25, $genericHits * 8);
            }

            $score += $this->labelSpecificAdjustment($field, $value);
            $score = max(0, min(100, $score));

            return [$key => [
                'label' => $label,
                'score' => $score,
                'status' => $score >= 75 ? 'strong' : ($score >= 45 ? 'mid' : 'weak'),
                'note' => $this->fieldNoteFor($field, $value, $score, $context),
            ]];
        });
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $inputs
     */
    private function completenessScore(Collection $fields, array $inputs): int
    {
        if ($fields->isEmpty()) {
            return 0;
        }

        $filled = $fields->filter(function (array $field, string $key) use ($inputs): bool {
            return trim((string) ($inputs[$key] ?? '')) !== '';
        })->count();

        return (int) round(($filled / max(1, $fields->count())) * 100);
    }

    /**
     * @param  Collection<string, array{score: int}>  $fieldAssessments
     */
    private function valueScore(Collection $fieldAssessments): int
    {
        $nonEmpty = $fieldAssessments->reject(fn (array $assessment): bool => $assessment['status'] === 'empty');

        if ($nonEmpty->isEmpty()) {
            return 0;
        }

        return (int) round($nonEmpty->avg('score') ?? 0);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  Collection<string, array{score: int, status: string}>  $fieldAssessments
     */
    private function coherenceScore(array $inputs, Collection $fieldAssessments): int
    {
        $filledValues = collect($inputs)
            ->filter(fn (mixed $value, mixed $key): bool => $key !== 'brief' && is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeForComparison($value))
            ->values();

        if ($filledValues->isEmpty()) {
            return 0;
        }

        $score = 78;

        if ($filledValues->unique()->count() < $filledValues->count()) {
            $score -= 25;
        }

        $weakCount = $fieldAssessments->filter(fn (array $assessment): bool => $assessment['status'] === 'weak')->count();
        $score -= min(25, $weakCount * 8);

        if ($filledValues->count() >= 3 && $filledValues->filter(fn (string $value): bool => mb_strlen($value) < 10)->count() >= 2) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $context
     * @param  Collection<string, array{status: string}>  $fieldAssessments
     */
    private function contextAlignmentScore(array $inputs, array $context, Collection $fieldAssessments): int
    {
        $profile = $context['workspace_profile'] ?? [];
        $contextText = implode(' ', array_filter([
            (string) ($profile['audience'] ?? ''),
            (string) ($profile['primary_goal'] ?? ''),
            (string) ($profile['country'] ?? ''),
            collect($context['tool_summaries'] ?? [])->pluck('headline')->implode(' '),
            collect($context['client_notes'] ?? [])->implode(' '),
        ]));

        if (trim($contextText) === '') {
            return 55;
        }

        $inputText = collect($inputs)
            ->filter(fn (mixed $value, mixed $key): bool => $key !== 'brief' && is_string($value) && trim($value) !== '')
            ->implode(' ');

        if (trim($inputText) === '') {
            return 0;
        }

        $score = 55;
        $overlap = $this->keywordOverlap($inputText, $contextText);
        $score += min(30, $overlap * 6);

        $weakCount = $fieldAssessments->filter(fn (array $assessment): bool => $assessment['status'] === 'weak')->count();
        $score -= min(20, $weakCount * 5);

        $audienceValue = trim((string) ($inputs['audience'] ?? $inputs['customer_type'] ?? $inputs['idea_audience'] ?? ''));
        if ($audienceValue !== '' && $this->looksGenericAudience($audienceValue) && trim((string) ($profile['audience'] ?? '')) !== '') {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * @param  Collection<string, array{label: string, score: int, status: string}>  $fieldAssessments
     * @return array<int, string>
     */
    private function strengths(Collection $fieldAssessments, int $completeness, int $contextAlignment): array
    {
        $items = [];

        if ($completeness >= 70) {
            $items[] = 'أغلب الحقول الأساسية مكتملة، وهذا يوفّر أرضية جيدة لبناء مخرج أوضح.';
        }

        if ($contextAlignment >= 65) {
            $items[] = 'المدخلات الحالية تبدو منسجمة إلى حد جيد مع سياق المشروع السابق، ما يقلل خطر التشتت.';
        }

        $fieldAssessments
            ->filter(fn (array $assessment): bool => $assessment['status'] === 'strong')
            ->take(2)
            ->each(function (array $assessment) use (&$items): void {
                $items[] = 'إجابة "'.$assessment['label'].'" فيها قدر جيد من التحديد ويمكن البناء عليها مباشرة.';
            });

        return array_values(array_unique($items));
    }

    /**
     * @param  Collection<string, array{label: string, score: int, status: string}>  $fieldAssessments
     * @return array<int, string>
     */
    private function gaps(Collection $fieldAssessments, int $completeness, int $value, int $coherence, int $contextAlignment): array
    {
        $items = [];

        if ($completeness < 60) {
            $items[] = 'المدخلات ما زالت ناقصة في حقول أساسية، وهذا يضعف جودة أي مخرج لاحق حتى لو كانت بعض الإجابات جيدة.';
        }

        if ($value < 60) {
            $items[] = 'بعض الإجابات ما زالت عامة أو وصفية، ولا تقدّم مادة كافية يمكن الاعتماد عليها استراتيجياً.';
        }

        if ($coherence < 60) {
            $items[] = 'هناك ضعف في الترابط بين الحقول، وبعض الإجابات تعيد الفكرة نفسها أو تترك فجوات منطقية بين السبب والهدف والنتيجة.';
        }

        if ($contextAlignment < 60) {
            $items[] = 'المدخلات الحالية لا تستفيد بما يكفي من سياق المشروع والأدوات السابقة، ما يهدد بتكرار عمل سبق إنجازه أو تجاهل معطيات مهمة.';
        }

        $fieldAssessments
            ->filter(fn (array $assessment): bool => $assessment['status'] === 'weak')
            ->take(3)
            ->each(function (array $assessment) use (&$items): void {
                $items[] = 'حقل "'.$assessment['label'].'" ما زال ضعيف القيمة أو قليل التحديد.';
            });

        return array_values(array_unique($items));
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fieldAssessments
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private function recommendations(Collection $fieldAssessments, Collection $fields, array $inputs, array $context): array
    {
        $items = [];

        $firstWeak = $fieldAssessments->first(fn (array $assessment): bool => $assessment['status'] === 'weak');
        if (is_array($firstWeak)) {
            $items[] = 'ابدأ بتحسين حقل "'.$firstWeak['label'].'" أولاً، لأنه حالياً لا يعطي الفريق أو الذكاء الاصطناعي مادة كافية للبناء.';
        }

        $emptyField = $fields->first(function (array $field, string $key) use ($inputs): bool {
            return trim((string) ($inputs[$key] ?? '')) === '';
        });
        if (is_array($emptyField)) {
            $items[] = 'أكمل سؤال "'.$emptyField['label'].'" بإجابة محددة، لأنه من الحقول التي تغيّر القرار فعلياً في هذه الأداة.';
        }

        $profileAudience = trim((string) (($context['workspace_profile']['audience'] ?? '')));
        if ($profileAudience !== '') {
            $items[] = 'اربط إجاباتك بالشريحة المحفوظة في المشروع: '.$profileAudience.'، أو اذكر بوضوح لماذا تختلف هذه الأداة عن ذلك السياق.';
        }

        if ($items === []) {
            $items[] = 'استمر في هذا المستوى من التحديد، ثم راجع هل كل إجابة تضيف قراراً أو دليلاً أو قيداً عملياً لا مجرد وصف عام.';
        }

        return array_slice(array_values(array_unique($items)), 0, 3);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function strategicNote(string $toolName, int $value, int $contextAlignment, array $context): string
    {
        $goal = (string) (($context['workspace_profile']['primary_goal'] ?? ''));
        $projectName = trim((string) (($context['project']['name'] ?? '')));
        $projectBit = $projectName !== '' ? 'لمشروع «'.$projectName.'»' : 'لهذا المشروع';

        if ($value < 60) {
            return 'قبل الاعتماد على مخرج «'.$toolName.'» '.$projectBit.'، ارفع دقة الإجابات؛ إلا فسيصبح الناتج عاماً وغير قابل للتنفيذ.';
        }

        if ($contextAlignment < 60) {
            $goalBit = $goal !== '' ? 'بهدف «'.$goal.'»' : 'بالأهداف المحفوظة للمشروع';

            return 'الحقول مملوءة لكنها ضعيفة الارتباط بما سبق؛ صِل «'.$toolName.'» '.$goalBit.' وبخلاصات الأدوات السابقة حتى لا تكرر عملاً أو تتجاهل وقائع المشروع.';
        }

        return 'المدخلات جيدة كأساس عملي؛ حافظ على نفس التحديد عند الحفظ حتى يبقى المخرج متسقاً '.$projectBit.'.';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     */
    private function fieldNoteFor(array $field, string $value, int $score, array $context): string
    {
        $label = (string) ($field['label'] ?? $field['key'] ?? 'هذا الحقل');
        $tip = trim((string) ($field['answer_tip'] ?? 'أضف معلومة محددة ومباشرة.'));

        if ($score >= 75) {
            return 'إجابة جيدة في "'.$label.'". حافظ على هذا المستوى من التحديد والوضوح.';
        }

        if ($this->genericPhraseHits($value) > 0) {
            return 'الإجابة الحالية في "'.$label.'" ما زالت عامة. '.$tip;
        }

        if ($this->looksGenericAudience($value) && $this->isAudienceField($field)) {
            return 'لا تكتفِ بوصف عام مثل "الجميع" أو "المشاريع الصغيرة". '.$tip;
        }

        if ($this->isGoalLikeField($field) && ! preg_match('/\d|خلال|أول|أسبوع|شهر|طلبات|عملاء|مبيعات|حجوزات/u', $value)) {
            return 'اجعل الإجابة في "'.$label.'" أقرب لنتيجة قابلة للملاحظة أو القياس، وليس اتجاهاً عاماً فقط.';
        }

        if ($this->isProblemLikeField($field) && ! preg_match('/لأن|بسبب|يؤدي|يمنع|يؤخر|يكلف/u', $value)) {
            return 'اذكر سبب المشكلة أو أثرها المباشر حتى لا تبقى الإجابة عنواناً عاماً فقط.';
        }

        $profileAudience = trim((string) (($context['workspace_profile']['audience'] ?? '')));
        if ($profileAudience !== '' && $this->isAudienceField($field) && ! $this->sharesKeyword($value, $profileAudience)) {
            return 'الإجابة في "'.$label.'" تبدو بعيدة عن الشريحة المحفوظة سابقاً. إن كنت تقصد شريحة أخرى فاذكر سبب هذا التغيير بوضوح.';
        }

        return 'الإجابة في "'.$label.'" مقبولة كبداية لكنها تحتاج مزيداً من التحديد أو المثال العملي. '.$tip;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function labelSpecificAdjustment(array $field, string $value): int
    {
        $adjustment = 0;

        if ($this->isAudienceField($field) && $this->looksGenericAudience($value)) {
            $adjustment -= 25;
        }

        if ($this->isGoalLikeField($field) && ! preg_match('/\d|خلال|أول|أسبوع|شهر|طلبات|عملاء|مبيعات|حجوزات/u', $value)) {
            $adjustment -= 15;
        }

        if ($this->isProblemLikeField($field) && mb_strlen($value) < 20) {
            $adjustment -= 12;
        }

        if ($this->isDifferentiationField($field) && preg_match('/إدارة محتوى|شراكة نمو|حلول عملية|خدمة متكاملة/u', $value)) {
            $adjustment -= 20;
        }

        return $adjustment;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isAudienceField(array $field): bool
    {
        $haystack = mb_strtolower(((string) ($field['key'] ?? '')).' '.((string) ($field['label'] ?? '')));

        return str_contains($haystack, 'audience')
            || str_contains($haystack, 'customer')
            || str_contains($haystack, 'segment')
            || str_contains($haystack, 'لمن')
            || str_contains($haystack, 'العميل')
            || str_contains($haystack, 'الشريحة');
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isGoalLikeField(array $field): bool
    {
        $haystack = mb_strtolower(((string) ($field['key'] ?? '')).' '.((string) ($field['label'] ?? '')));

        return str_contains($haystack, 'goal')
            || str_contains($haystack, 'result')
            || str_contains($haystack, 'outcome')
            || str_contains($haystack, 'الهدف')
            || str_contains($haystack, 'النتيجة');
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isProblemLikeField(array $field): bool
    {
        $haystack = mb_strtolower(((string) ($field['key'] ?? '')).' '.((string) ($field['label'] ?? '')));

        return str_contains($haystack, 'problem')
            || str_contains($haystack, 'bottleneck')
            || str_contains($haystack, 'gap')
            || str_contains($haystack, 'objection')
            || str_contains($haystack, 'المشكلة')
            || str_contains($haystack, 'المعطل')
            || str_contains($haystack, 'العائق')
            || str_contains($haystack, 'الاعتراض');
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isDifferentiationField(array $field): bool
    {
        $haystack = mb_strtolower(((string) ($field['key'] ?? '')).' '.((string) ($field['label'] ?? '')));

        return str_contains($haystack, 'difference')
            || str_contains($haystack, 'unique')
            || str_contains($haystack, 'angle')
            || str_contains($haystack, 'الميزة')
            || str_contains($haystack, 'التميز')
            || str_contains($haystack, 'يختلف');
    }

    private function looksGenericAudience(string $value): bool
    {
        return preg_match('/الجميع|الكل|كل\s+الناس|كل\s+العملاء|المشاريع\s+الصغيرة|أصحاب\s+الاعمال|أصحاب\s+الأعمال/u', $value) === 1;
    }

    private function genericPhraseHits(string $text): int
    {
        $patterns = [
            '/حلول\s+(?:عملية|متكاملة)/u',
            '/تحسين\s+(?:الحضور|النتائج)/u',
            '/نسبة\s+نجاح\s+عالية/u',
            '/عملاء\s+يثقون\s+بنا/u',
            '/الأفضل|مميز|احترافي/u',
            '/شراكة\s+نمو/u',
            '/إدارة\s+محتوى\s+شهرية/u',
        ];

        $hits = 0;
        foreach ($patterns as $pattern) {
            $hits += preg_match_all($pattern, $text) ?: 0;
        }

        return $hits;
    }

    private function wordCount(string $text): int
    {
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($tokens, fn (?string $token): bool => $token !== null && $token !== ''));
    }

    private function keywordOverlap(string $left, string $right): int
    {
        $leftTokens = $this->keywords($left);
        $rightTokens = $this->keywords($right);

        return count(array_intersect($leftTokens, $rightTokens));
    }

    /**
     * @return array<int, string>
     */
    private function keywords(string $text): array
    {
        $normalized = preg_replace('/[^\p{Arabic}\p{Latin}\p{N}\s]/u', ' ', mb_strtolower($text)) ?? $text;
        $tokens = preg_split('/\s+/u', trim($normalized)) ?: [];

        return array_values(array_unique(array_filter($tokens, function (?string $token): bool {
            if ($token === null || $token === '' || mb_strlen($token) < 4) {
                return false;
            }

            return ! in_array($token, ['هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'لأن', 'بسبب', 'الى', 'على'], true);
        })));
    }

    private function sharesKeyword(string $left, string $right): bool
    {
        return $this->keywordOverlap($left, $right) > 0;
    }

    private function normalizeForComparison(string $text): string
    {
        $text = preg_replace('/[^\p{Arabic}\p{Latin}\p{N}\s]/u', ' ', mb_strtolower($text)) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        return $text;
    }

    private function verdictFor(int $overall, int $completeness, int $value, int $coherence, int $contextAlignment): string
    {
        if ($overall >= 80) {
            return 'المدخلات قوية وقابلة للبناء عليها، مع ترابط جيد وسياق واضح.';
        }

        if ($value < 55) {
            return 'الحقول قد تكون ممتلئة جزئياً، لكن القيمة الاستراتيجية للإجابات ما زالت ضعيفة أو عامة.';
        }

        if ($contextAlignment < 55) {
            return 'الإجابات الحالية تحتاج ربطاً أوضح بما سبق في المشروع حتى لا يخرج الناتج منفصلاً عن الواقع.';
        }

        if ($completeness < 60) {
            return 'هناك بداية جيدة، لكن نقص بعض الحقول الأساسية ما زال يمنع تكوين صورة قوية ومتسقة.';
        }

        if ($coherence < 60) {
            return 'الإجابات تحتوي مواد واعدة، لكن الترابط بينها ما زال يحتاج صقلاً حتى تخدم قراراً واحداً واضحاً.';
        }

        return 'المدخلات مقبولة كبداية، لكنها تحتاج مزيداً من التحديد والربط حتى تصبح وقوداً قوياً للمخرجات.';
    }
}
