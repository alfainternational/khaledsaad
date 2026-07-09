<?php

namespace App\Support\Tooling;

use App\Support\AI\WorkspaceGenerationContextBuilder;
use Illuminate\Support\Collection;

class ToolInputQualityAssessmentService
{
    public function __construct(
        private readonly ToolBlueprintCatalog $blueprints,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
        private readonly \App\Domain\AI\Services\QualityJudge $judge,
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

        // تقييم كل حقل دلالياً عبر Gemini بنداء واحد (مُكاش): يحكم هل تُجيب الإجابة
        // *هذا السؤال تحديداً* وهل محدّدة — لا الطول ولا الكلمات. يستبدل الدرجات
        // المحلية التقريبية بدرجات دقيقة تكشف الإجابة خارج الموضوع أو غير المطابقة للسؤال.
        $valueNote = 'هل إجاباتك تجيب الأسئلة فعلاً وتكون محددة؟';
        $valueSource = 'local';
        $perField = $this->judgeFields($fields, $inputs, $toolName);
        if ($perField !== null) {
            $valueSource = 'gemini';
            $fieldAssessments = $fieldAssessments->map(function (array $a, string $key) use ($perField): array {
                if (isset($perField[$key]) && is_array($perField[$key])) {
                    $score = max(0, min(100, (int) ($perField[$key]['score'] ?? $a['score'])));
                    $a['score'] = $score;
                    $a['status'] = $score >= 75 ? 'strong' : ($score >= 45 ? 'mid' : 'weak');
                    $note = trim((string) ($perField[$key]['note'] ?? ''));
                    if ($note !== '') {
                        $a['note'] = $note;
                    }
                }

                return $a;
            });
        }

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
                    'label' => 'هل أكملت إجاباتك؟',
                    'score' => $completeness,
                    'note' => 'هل عبّأت الحقول المهمة بما يكفي لتنتقل للخطوة التالية؟',
                ],
                [
                    'key' => 'value',
                    'label' => 'هل إجاباتك واضحة؟',
                    'score' => $value,
                    'note' => $valueNote,
                    'source' => $valueSource,
                ],
                [
                    'key' => 'coherence',
                    'label' => 'هل إجاباتك متّسقة؟',
                    'score' => $coherence,
                    'note' => 'هل تكمّل إجاباتك بعضها، أم تكرّر الفكرة نفسها أو تتعارض؟',
                ],
                [
                    'key' => 'context_alignment',
                    'label' => 'هل تناسب مشروعك؟',
                    'score' => $contextAlignment,
                    'note' => 'هل تناسب إجاباتك ما يخص مشروعك وما أنجزته من أدوات سابقة؟',
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
            $tip = trim((string) ($field['answer_tip'] ?? 'اكتب معلومة واضحة يمكن البناء عليها.'));

            if ($value === '') {
                return [$key => [
                    'label' => $label,
                    'score' => 0,
                    'status' => 'empty',
                    'note' => 'لم تكتب شيئاً هنا بعد. '.$tip,
                ]];
            }

            // التقييم على الجودة لا الطول: نقطة انطلاق محايدة، ثم إشارات مضمون.
            $score = 50;
            $wordCount = $this->wordCount($value);
            $genericHits = $this->genericPhraseHits($value);

            // صلة الإجابة بما يطلبه الحقل فعلاً (تعليماته) — جوهر الجودة.
            $score += $this->instructionRelevance($field, $value);

            // إشارات تحديد ملموسة (أرقام/زمن فقط — لا أسماء كيانات تظهر في الحشو).
            if (preg_match('/\d|%|ريال|دولار|خلال|أسبوع|شهر|يوم/u', $value)) {
                $score += 12;
            }

            // عقوبة الحشو العام (المشكلة الحقيقية، لا قِصَر النص).
            if ($genericHits > 0) {
                $score -= min(40, $genericHits * 14);
            }

            // مجهود صفري حقيقي: كلمة واحدة أو تكرار حرفي لعنوان الحقل.
            if ($wordCount <= 1 || $this->echoesLabel($label, $value)) {
                $score -= 18;
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
            $items[] = 'أكملت معظم الحقول المهمة، وهذا يعطيك أساساً جيداً لنتيجة أوضح.';
        }

        if ($contextAlignment >= 65) {
            $items[] = 'إجاباتك تناسب ما يخص مشروعك سابقاً بشكل جيد، وهذا يبقي شغلك مركّزاً.';
        }

        $fieldAssessments
            ->filter(fn (array $assessment): bool => $assessment['status'] === 'strong')
            ->take(2)
            ->each(function (array $assessment) use (&$items): void {
                $items[] = 'إجابتك في "'.$assessment['label'].'" واضحة ومحددة، ويمكن البناء عليها مباشرة.';
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
            $items[] = 'ما زلت لم تكمل حقولاً مهمة، وهذا يضعف النتيجة حتى لو كانت بعض إجاباتك جيدة.';
        }

        if ($value < 60) {
            $items[] = 'بعض إجاباتك ما زالت عامة، ولا تعطيك مادة كافية لتبني عليها قرارك.';
        }

        if ($coherence < 60) {
            $items[] = 'إجاباتك ليست متّسقة بما يكفي؛ بعضها يكرّر الفكرة نفسها أو يترك فجوة بين السبب والهدف والنتيجة.';
        }

        if ($contextAlignment < 60) {
            $items[] = 'إجاباتك لا تستفيد بما يكفي مما يخص مشروعك وأدواتك السابقة، وقد تكرّر شغلاً أنجزته أو تتجاهل معلومات مهمة.';
        }

        $fieldAssessments
            ->filter(fn (array $assessment): bool => $assessment['status'] === 'weak')
            ->take(3)
            ->each(function (array $assessment) use (&$items): void {
                $items[] = 'إجابتك في "'.$assessment['label'].'" ما زالت عامة أو قليلة التفاصيل.';
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
            $items[] = 'ابدأ بتحسين إجابتك في "'.$firstWeak['label'].'"، فهي حالياً لا تعطي مادة كافية للبناء عليها.';
        }

        $emptyField = $fields->first(function (array $field, string $key) use ($inputs): bool {
            return trim((string) ($inputs[$key] ?? '')) === '';
        });
        if (is_array($emptyField)) {
            $items[] = 'أكمل سؤال "'.$emptyField['label'].'" بإجابة واضحة، فهو من الأسئلة التي تغيّر النتيجة فعلاً في هذه الأداة.';
        }

        $profileAudience = trim((string) (($context['workspace_profile']['audience'] ?? '')));
        if ($profileAudience !== '') {
            $items[] = 'اربط إجاباتك بالعميل المحفوظ في مشروعك: '.$profileAudience.'، أو وضّح لماذا تختلف هذه الأداة عن ذلك.';
        }

        if ($items === []) {
            $items[] = 'استمر بهذا الوضوح، وراجع هل كل إجابة تضيف فعلاً قراراً أو دليلاً أو شيئاً عملياً، لا مجرد وصف عام.';
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
            return 'قبل أن تعتمد على نتيجة «'.$toolName.'» '.$projectBit.'، اجعل إجاباتك أدق؛ وإلا ستخرج النتيجة عامة ويصعب تنفيذها.';
        }

        if ($contextAlignment < 60) {
            $goalBit = $goal !== '' ? 'بهدف «'.$goal.'»' : 'بأهداف مشروعك المحفوظة';

            return 'أكملت الحقول لكنها بعيدة عمّا أنجزته سابقاً؛ اربط «'.$toolName.'» '.$goalBit.' وبما خرجت به من أدوات سابقة حتى لا تكرّر شغلك أو تتجاهل واقع مشروعك.';
        }

        return 'إجاباتك أساس عملي جيد؛ حافظ على نفس الوضوح عند الحفظ حتى تبقى النتيجة متّسقة '.$projectBit.'.';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $context
     */
    private function fieldNoteFor(array $field, string $value, int $score, array $context): string
    {
        $label = (string) ($field['label'] ?? $field['key'] ?? 'هذا الحقل');
        $tip = trim((string) ($field['answer_tip'] ?? 'أضف معلومة واضحة ومباشرة.'));

        if ($score >= 75) {
            return 'إجابة جيدة في "'.$label.'". حافظ على هذا الوضوح والتحديد.';
        }

        if ($this->genericPhraseHits($value) > 0) {
            return 'إجابتك في "'.$label.'" ما زالت عامة. '.$tip;
        }

        if ($this->looksGenericAudience($value) && $this->isAudienceField($field)) {
            return 'لا تكتفِ بوصف عام مثل "الجميع" أو "المشاريع الصغيرة". '.$tip;
        }

        if ($this->isGoalLikeField($field) && ! preg_match('/\d|خلال|أول|أسبوع|شهر|طلبات|عملاء|مبيعات|حجوزات/u', $value)) {
            return 'اجعل إجابتك في "'.$label.'" أقرب لنتيجة تلاحظها أو تقيسها، لا مجرد اتجاه عام.';
        }

        if ($this->isProblemLikeField($field) && ! preg_match('/لأن|بسبب|يؤدي|يمنع|يؤخر|يكلف/u', $value)) {
            return 'اذكر سبب المشكلة أو أثرها المباشر حتى لا تبقى إجابتك عنواناً عاماً فقط.';
        }

        $profileAudience = trim((string) (($context['workspace_profile']['audience'] ?? '')));
        if ($profileAudience !== '' && $this->isAudienceField($field) && ! $this->sharesKeyword($value, $profileAudience)) {
            return 'إجابتك في "'.$label.'" تبدو بعيدة عن العميل المحفوظ سابقاً. إن كنت تقصد عميلاً آخر فوضّح سبب هذا التغيير.';
        }

        return 'إجابتك في "'.$label.'" مقبولة كبداية لكنها تحتاج مزيداً من التحديد أو مثالاً عملياً. '.$tip;
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
            // حشو تسويقي عام شائع (يقلّل الجودة بغضّ النظر عن الطول):
            '/مبتكر|متميّ?ز|عالي[ةه]?\s*الجودة|الجودة\s*العالية|الكرام|رائد[ةه]?/u',
            '/في\s*كل\s*مكان|بإذن\s*الله|دائم[اًا]*\s*وأبد|على\s*أعلى\s*مستوى/u',
            '/نخدم\s*الجميع|جميع\s*(?:العملاء|الناس)|لكل\s*(?:الناس|الفئات)/u',
        ];

        $hits = 0;
        foreach ($patterns as $pattern) {
            $hits += preg_match_all($pattern, $text) ?: 0;
        }

        return $hits;
    }

    /**
     * تقييم جودة الاستمارة عبر قاضي Gemini (نداء واحد، مُكاش). يعيد null عند
     * التعطيل/غياب LLM أو قلّة المدخلات، فيتدهور التقييم للقاعدة المحلية بأمان.
     *
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $inputs
     * @return array{score: int, note: string}|null
     */
    private function judgeFields(Collection $fields, array $inputs, string $toolName): ?array
    {
        if (! $this->judge->enabled()) {
            return null;
        }

        $items = [];
        foreach ($fields as $key => $field) {
            $value = trim((string) ($inputs[$key] ?? ''));
            if ($value !== '') {
                $items[] = [
                    'key' => (string) $key,
                    'question' => (string) ($field['label'] ?? $key),
                    'value' => $value,
                ];
            }
        }

        // لا نزعج المستخدم بنداء LLM قبل أن يكتب ما يكفي للحكم على الجودة.
        if (count($items) < 1) {
            return null;
        }

        return $this->judge->scoreFields($toolName, $items);
    }

    /**
     * صلة الإجابة بتعليمات الحقل (answer_tip + label): قياس جودة لا طول.
     * يعالج الكلمات الدالة في "ما هو مطلوب" مقابل ما كتبه المستخدم.
     *
     * @param  array<string, mixed>  $field
     * @return int  0..20
     */
    private function instructionRelevance(array $field, string $value): int
    {
        $instruction = trim((string) ($field['answer_tip'] ?? '')).' '.(string) ($field['label'] ?? '');
        $instruction = trim($instruction);
        if ($instruction === '') {
            return 8;
        }

        // نتجاهل أمثلة "مثال: ..." حتى لا نكافئ نسخ المثال حرفياً.
        $instruction = preg_replace('/مثال\s*:.*/u', '', $instruction) ?? $instruction;

        $overlap = $this->keywordOverlap($value, $instruction);

        return max(0, min(20, $overlap * 7));
    }

    private function echoesLabel(string $label, string $value): bool
    {
        $l = trim(mb_strtolower($label));
        $v = trim(mb_strtolower($value));

        return $l !== '' && ($v === $l || $v === rtrim($l, '؟?.'));
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
            return 'إجاباتك قوية ويمكن البناء عليها، متّسقة فيما بينها وتناسب مشروعك.';
        }

        if ($value < 55) {
            return 'ربما عبّأت بعض الحقول، لكن إجاباتك ما زالت ضعيفة أو عامة.';
        }

        if ($contextAlignment < 55) {
            return 'إجاباتك تحتاج ربطاً أوضح بما أنجزته في مشروعك حتى لا تخرج النتيجة بعيدة عن واقعك.';
        }

        if ($completeness < 60) {
            return 'بداية جيدة، لكن نقص بعض الحقول المهمة ما زال يمنعك من تكوين صورة قوية ومتّسقة.';
        }

        if ($coherence < 60) {
            return 'إجاباتك فيها مادة واعدة، لكنها تحتاج صقلاً حتى تتّسق وتخدم قراراً واحداً واضحاً.';
        }

        return 'إجاباتك مقبولة كبداية، لكنها تحتاج مزيداً من الوضوح والترابط حتى تخرج نتيجة قوية.';
    }
}
