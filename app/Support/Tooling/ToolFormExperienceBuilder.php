<?php

namespace App\Support\Tooling;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\StageCatalog;
use Illuminate\Support\Str;

class ToolFormExperienceBuilder
{
    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     * @return array<string, mixed>
     */
    public function build(
        Tool $tool,
        array $blueprint,
        array $profile,
        ?Project $project = null,
        ?ToolRun $latestRun = null,
        array $upstreamContext = [],
    ): array {
        $modes = [];

        foreach (($blueprint['modes'] ?? []) as $modeKey => $mode) {
            $modes[$modeKey] = $this->buildModeExperience(
                $modeKey,
                $mode,
                $tool,
                $profile,
                $project,
                $latestRun,
                $upstreamContext,
            );
        }

        return [
            'summary' => $this->buildSummary($tool, $blueprint, $profile, $project, $latestRun, $upstreamContext, $modes),
            'modes' => $modes,
        ];
    }

    /**
     * @param  array<string, mixed>  $mode
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     * @return array<string, mixed>
     */
    private function buildModeExperience(
        string $modeKey,
        array $mode,
        Tool $tool,
        array $profile,
        ?Project $project,
        ?ToolRun $latestRun,
        array $upstreamContext,
    ): array {
        $fields = [];

        foreach (($mode['fields'] ?? []) as $index => $field) {
            $category = $this->categoryForField($field);
            $priority = $this->priorityForField($category, $modeKey, $index);
            $quality = $this->qualityRulesForField($category, $field);

            $fields[$field['key']] = [
                'category' => $category,
                'priority' => $priority,
                'priority_label' => $this->priorityLabel($priority),
                'context_hint' => $this->contextHintForField($category, $field, $tool, $profile, $project, $latestRun, $upstreamContext),
                'smart_placeholder' => $this->smartPlaceholderForField($category, $field, $profile, $project, $latestRun, $upstreamContext),
                'suggested_value' => $this->suggestedValueForField($category, $field, $profile, $project, $latestRun, $upstreamContext),
                'suggestion_label' => $this->suggestionLabelForPriority($priority),
                'empty_prompt' => $this->emptyPromptForField($category, $field),
                'weak_prompt' => $this->weakPromptForField($category, $quality['min_length']),
                'quality' => $quality,
            ];
        }

        $criticalField = collect($fields)->first(fn (array $fieldMeta) => $fieldMeta['priority'] === 'critical');
        $criticalCount = collect($fields)->filter(fn (array $fieldMeta) => $fieldMeta['priority'] === 'critical')->count();

        return [
            'focus_title' => 'كيف تعبئ هذا الوضع؟',
            'focus_note' => $this->modeFocusNote($modeKey, $tool, $profile, $project, $upstreamContext),
            'focus_points' => array_values(array_filter([
                $project ? 'اربط كل إجابة بمشروع '.$project->name.' لا بوصف عام للمجال.' : null,
                ! empty($profile['audience']) ? 'الجمهور المرجعي الحالي: '.$profile['audience'] : null,
                ! empty($profile['primary_goal']) ? 'الهدف الحالي الذي يجب أن تخدمه الإجابات: '.$this->displayPrimaryGoal($profile['primary_goal'] ?? null) : null,
                $criticalCount > 0 ? 'ابدأ بالحقول الأساسية أولاً ثم انتقل إلى الحقول الداعمة.' : null,
            ])),
            'critical_count' => $criticalCount,
            'first_critical_label' => $criticalField['priority_label'] ?? null,
            'fields' => $fields,
        ];
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     * @param  array<string, mixed>  $modes
     * @return array<string, mixed>
     */
    private function buildSummary(
        Tool $tool,
        array $blueprint,
        array $profile,
        ?Project $project,
        ?ToolRun $latestRun,
        array $upstreamContext,
        array $modes,
    ): array {
        $currentMode = array_key_first($modes) ?: 'guided';
        $firstCriticalField = collect($modes)
            ->flatMap(fn (array $mode) => collect($mode['fields'] ?? [])->map(fn (array $meta, string $key) => [
                'key' => $key,
                ...$meta,
            ]))
            ->first(fn (array $field) => $field['priority'] === 'critical');

        $latestHeadline = (string) data_get($latestRun?->summary_json ?? [], 'headline', '');
        $upstreamHeadline = (string) ($upstreamContext[0]['headline'] ?? '');
        $stageLabel = StageCatalog::label((int) $tool->stage);

        return [
            'title' => 'مدخلات أذكى لهذه الأداة',
            'intro' => $project
                ? 'المدخلات هنا يجب أن تخدم قراراً عملياً داخل مشروع '.$project->name.' ضمن '.$stageLabel.'.'
                : 'املأ الحقول بصورة تخدم قراراً عملياً حقيقياً، لا مجرد وصف عام.',
            'bullets' => array_values(array_filter([
                ! empty($profile['primary_goal']) ? 'اجعل كل إجابة تقرّبك من الهدف الحالي: '.$this->displayPrimaryGoal($profile['primary_goal'] ?? null).'.' : null,
                ! empty($profile['audience']) ? 'اكتب بلغة مرتبطة بجمهورك الفعلي: '.$profile['audience'].'.' : null,
                $upstreamHeadline !== '' ? 'استفد من المخرج السابق: '.$upstreamHeadline.'.' : null,
                $latestHeadline !== '' ? 'آخر مخرج محفوظ لهذه الأداة: '.$latestHeadline.'.' : null,
                $firstCriticalField ? 'ابدأ بالحقل الأهم أولاً: '.$this->humanizeFieldKey($firstCriticalField['key']).'.' : null,
            ])),
            'focus_field' => $firstCriticalField['key'] ?? null,
            'focus_label' => $this->humanizeFieldKey($firstCriticalField['key'] ?? null),
            'project_label' => $project?->name,
            'client_label' => $project?->client?->name,
            'mode_label' => $blueprint['modes'][$currentMode]['label'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function categoryForField(array $field): string
    {
        $haystack = Str::lower(trim(implode(' ', array_filter([
            $field['key'] ?? '',
            $field['label'] ?? '',
            $field['placeholder'] ?? '',
        ]))));

        return match (true) {
            str_contains($haystack, 'audience'), str_contains($haystack, 'customer'), str_contains($haystack, 'segment'), str_contains($haystack, 'الجمهور'), str_contains($haystack, 'الشريحة'), str_contains($haystack, 'العميل') => 'audience',
            str_contains($haystack, 'لماذا'), str_contains($haystack, 'why'), (str_contains($haystack, 'reason') && (str_contains($haystack, 'goal') || str_contains($haystack, 'هدف'))) => 'goal_rationale',
            str_contains($haystack, 'goal'), str_contains($haystack, 'priority'), str_contains($haystack, 'هدف'), str_contains($haystack, 'أولوية') => 'goal',
            str_contains($haystack, 'result'), str_contains($haystack, 'outcome'), str_contains($haystack, 'offer_result'), str_contains($haystack, 'النتيجة') => 'result',
            str_contains($haystack, 'problem'), str_contains($haystack, 'gap'), str_contains($haystack, 'bottleneck'), str_contains($haystack, 'obstacle'), str_contains($haystack, 'المشكلة'), str_contains($haystack, 'الفجوة'), str_contains($haystack, 'العائق') => 'problem',
            str_contains($haystack, 'offer'), str_contains($haystack, 'package'), str_contains($haystack, 'promise'), str_contains($haystack, 'العرض'), str_contains($haystack, 'الباقة'), str_contains($haystack, 'الوعد') => 'offer',
            str_contains($haystack, 'price'), str_contains($haystack, 'pricing'), str_contains($haystack, 'السعر'), str_contains($haystack, 'التسعير') => 'pricing',
            str_contains($haystack, 'difference'), str_contains($haystack, 'unique'), str_contains($haystack, 'ميزة'), str_contains($haystack, 'تمي') => 'difference',
            str_contains($haystack, 'proof'), str_contains($haystack, 'evidence'), str_contains($haystack, 'دليل'), str_contains($haystack, 'مصداق') => 'proof',
            str_contains($haystack, 'market'), str_contains($haystack, 'country'), str_contains($haystack, 'السوق'), str_contains($haystack, 'الدولة') => 'market',
            str_contains($haystack, 'channel'), str_contains($haystack, 'campaign'), str_contains($haystack, 'قناة'), str_contains($haystack, 'حملة') => 'channel',
            str_contains($haystack, 'metric'), str_contains($haystack, 'kpi'), str_contains($haystack, 'indicator'), str_contains($haystack, 'مؤشر'), str_contains($haystack, 'قياس') => 'metric',
            str_contains($haystack, 'risk'), str_contains($haystack, 'threat'), str_contains($haystack, 'constraint'), str_contains($haystack, 'خطر'), str_contains($haystack, 'تهديد'), str_contains($haystack, 'قيد') => 'risk',
            str_contains($haystack, 'timing'), str_contains($haystack, 'deadline'), str_contains($haystack, 'متى'), str_contains($haystack, 'وقت'), str_contains($haystack, 'زمن') => 'timing',
            default => 'general',
        };
    }

    private function priorityForField(string $category, string $modeKey, int $index): string
    {
        if ($index === 0) {
            return 'critical';
        }

        return match ($category) {
            'audience', 'goal', 'goal_rationale', 'result', 'problem', 'offer', 'pricing' => 'critical',
            'difference', 'proof', 'metric', 'risk', 'timing', 'market' => $modeKey === 'expert' ? 'important' : 'critical',
            default => $modeKey === 'guided' ? 'important' : 'supporting',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function qualityRulesForField(string $category, array $field): array
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'select') {
            return [
                'min_length' => 0,
                'generic_terms' => [],
            ];
        }

        $genericTerms = ['جيد', 'ممتاز', 'مناسب', 'عادي', 'أي شيء', 'لا أعرف', 'كل شيء', 'التسويق', 'المحتوى'];

        return match ($category) {
            'audience', 'goal', 'goal_rationale', 'result', 'problem', 'offer' => [
                'min_length' => 18,
                'generic_terms' => array_merge($genericTerms, ['الكل', 'الجميع', 'أي عميل', 'زيادة المبيعات']),
            ],
            'proof', 'difference', 'pricing', 'market', 'channel', 'risk', 'metric' => [
                'min_length' => 12,
                'generic_terms' => array_merge($genericTerms, ['منصة مناسبة', 'سعر مناسب', 'نتائج جيدة']),
            ],
            default => [
                'min_length' => 10,
                'generic_terms' => $genericTerms,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     */
    private function contextHintForField(
        string $category,
        array $field,
        Tool $tool,
        array $profile,
        ?Project $project,
        ?ToolRun $latestRun,
        array $upstreamContext,
    ): string {
        $projectLabel = $project?->name ? ' في مشروع '.$project->name : '';
        $audience = trim((string) ($profile['audience'] ?? ''));
        $goal = trim((string) ($profile['primary_goal'] ?? ''));
        $goalDisplay = $this->displayPrimaryGoal($profile['primary_goal'] ?? null);
        $fieldLabel = trim((string) ($field['label'] ?? ''));
        $country = trim((string) ($profile['country'] ?? ''));
        $latestHeadline = trim((string) data_get($latestRun?->summary_json ?? [], 'headline', ''));
        $upstreamHeadline = trim((string) ($upstreamContext[0]['headline'] ?? ''));

        return match ($category) {
            'audience' => $audience !== ''
                ? 'اكتب الشريحة الأقرب للشراء فعلاً'.$projectLabel.'، والمرجع الحالي في المساحة هو: '.$audience.'.'
                : 'لا تكتب جمهوراً عاماً. حدّد من يشتري أولاً'.$projectLabel.' وما الذي يميّزه.',
            'goal' => $this->goalOutcomeContextHint($fieldLabel, $projectLabel, $goal, $goalDisplay, $audience),
            'goal_rationale' => $this->goalRationaleContextHint($fieldLabel, $projectLabel, $goalDisplay, $goal),
            'result' => $this->measurableResultContextHint($fieldLabel, $projectLabel, $goal, $goalDisplay, $audience),
            'problem' => $upstreamHeadline !== ''
                ? 'استفد من المخرجات السابقة، خصوصاً: '.$upstreamHeadline.'. صف المشكلة أو العائق من زاوية القرار التالي.'
                : 'اذكر المشكلة مع أثرها المباشر وما الذي تعطل بسببه'.$projectLabel.'.',
            'offer' => $latestHeadline !== ''
                ? 'احرص أن يكون هذا الحقل متسقاً مع آخر مخرج محفوظ: '.$latestHeadline.'.'
                : 'اكتب ما سيفهمه العميل بسرعة: ماذا سيأخذ، ولماذا يهمه الآن.',
            'pricing' => 'اجعل الإجابة مرتبطة بقيمة العرض'.$projectLabel.' لا بالتكلفة فقط أو الانطباع العام.',
            'difference' => 'اذكر فرقاً حقيقياً يمكن شرحه وإثباته، لا مجرد وصف تسويقي متكرر.',
            'proof' => 'أضف شيئاً يمكن استخدامه كدليل أو عنصر ثقة: نتيجة، خبرة، حالة مشابهة، أو آلية واضحة.',
            'market' => $country !== ''
                ? 'اجعل القراءة السوقية مرتبطة بالسوق المرجعي الحالي: '.$country.'.'
                : 'سمِّ السوق أو الجزء السوقي بوضوح، لا تكتب السوق بشكل واسع ومبهم.',
            'channel' => 'حدّد قناة أو مساراً قابلاً للتنفيذ فعلاً'.$projectLabel.'، وليس عنواناً عاماً مثل "السوشيال".',
            'metric' => 'اختر مؤشراً واحداً يمكن مراجعته لاحقاً، لا مجموعة مؤشرات مشتتة.',
            'risk' => 'اذكر خطراً فعلياً يمكن أن يعطل القرار، لا خوفاً عاماً أو افتراضاً فضفاضاً.',
            'timing' => 'ضع إطاراً زمنياً يساعد على القرار، مثل أسبوع أو شهر أو 90 يوماً.',
            default => 'اكتب إجابة يمكن البناء عليها في قرار أو مخرج حقيقي داخل '.$tool->name.'.',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     */
    private function smartPlaceholderForField(
        string $category,
        array $field,
        array $profile,
        ?Project $project,
        ?ToolRun $latestRun,
        array $upstreamContext,
    ): string {
        $original = (string) ($field['placeholder'] ?? '');
        $audience = trim((string) ($profile['audience'] ?? ''));
        $goal = trim((string) ($profile['primary_goal'] ?? ''));
        $goalDisplay = $this->displayPrimaryGoal($profile['primary_goal'] ?? null);
        $country = trim((string) ($profile['country'] ?? ''));
        $upstreamHeadline = trim((string) ($upstreamContext[0]['headline'] ?? ''));
        $latestHeadline = trim((string) data_get($latestRun?->summary_json ?? [], 'headline', ''));

        return match ($category) {
            'audience' => $audience !== '' ? 'مثال قريب من بياناتك: '.$audience : $original,
            'goal' => $this->smartGoalOutcomePlaceholder($field, $original, $project, $audience),
            'goal_rationale' => $original !== '' ? $original : 'مثال: لأن تحقيقه يثبت أن العرض مطلوب قبل زيادة الإنفاق أو فتح قنوات جديدة.',
            'result' => $this->smartMeasurableResultPlaceholder($field, $original, $project, $audience),
            'market' => $country !== '' ? 'مثال مرتبط بسوقك الحالي: '.$country : $original,
            'problem' => $upstreamHeadline !== '' ? 'مثال من سياق مشروعك: '.$upstreamHeadline : $original,
            'offer' => $latestHeadline !== '' ? 'اربط الصياغة بهذا الاتجاه: '.$latestHeadline : $original,
            default => $original !== '' ? $original : ($project ? 'اكتب إجابة مرتبطة بمشروع '.$project->name : ''),
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     */
    private function suggestedValueForField(
        string $category,
        array $field,
        array $profile,
        ?Project $project,
        ?ToolRun $latestRun,
        array $upstreamContext,
    ): ?string {
        $audience = trim((string) ($profile['audience'] ?? ''));
        $country = trim((string) ($profile['country'] ?? ''));
        $locale = trim((string) ($profile['content_locale'] ?? ''));
        $upstreamHeadline = trim((string) ($upstreamContext[0]['headline'] ?? ''));

        return match ($category) {
            'audience' => $audience !== '' ? $audience : null,
            'goal' => $this->suggestGoalFieldSnippet($field, $profile, $project, $upstreamHeadline),
            'goal_rationale' => $this->suggestGoalRationaleSnippet($project),
            'result' => $this->suggestResultFieldSnippet($field, $profile, $project, $upstreamHeadline),
            'market' => $country !== '' ? $country : null,
            'problem', 'offer' => $upstreamHeadline !== '' ? Str::limit($upstreamHeadline, 140, '') : null,
            'timing' => 'خلال 30 يوماً',
            'channel' => $locale !== '' ? ContentLocaleCatalog::label($locale) : null,
            default => null,
        };
    }

    private function suggestionLabelForPriority(string $priority): string
    {
        return match ($priority) {
            'critical' => 'استخدم مرجع المشروع',
            'important' => 'ابدأ بهذه الصياغة',
            default => 'طبّق اقتراحاً سريعاً',
        };
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function emptyPromptForField(string $category, array $field): string
    {
        return match ($category) {
            'audience' => 'هذا الحقل أساسي لأن بقية الرسالة والعرض ستُبنى عليه.',
            'goal', 'result' => 'بدون هذا الحقل ستبقى الأداة عامة ولن تعرف ما القرار الذي تخدمه.',
            'goal_rationale' => 'السبب العملي يحوّل الهدف من رغبة إلى قرار: لماذا هذه الأولوية وليس غيرها الآن؟',
            'problem' => 'صف المشكلة أو الفجوة قبل الانتقال إلى الحل أو التوصية.',
            'offer', 'pricing' => 'وضّح هذا الحقل حتى لا يبقى المخرج نظرياً أو غير قابل للبيع.',
            default => 'أكمل هذا الحقل حتى تصبح الصورة أدق وأكثر قابلية للاستخدام.',
        };
    }

    private function weakPromptForField(string $category, int $minLength): string
    {
        return match ($category) {
            'audience' => 'الإجابة الحالية ما زالت عامة. اجعل الشريحة أوضح وأكثر قرباً من الشراء.',
            'goal', 'result' => 'اجعل النتيجة أكثر تحديداً، ويفضل أن تتضمن زمناً أو معيار نجاح.',
            'goal_rationale' => 'اربط السبب بقرار عملي (وقت، تكلفة، مخاطرة) وليس بجملة تحفيزية عامة.',
            'problem' => 'صف المشكلة مع أثرها أو سببها، لا بعنوان مختصر فقط.',
            'offer', 'pricing' => 'اربط الإجابة بالقيمة أو النتيجة، لا بوصف قصير أو عام.',
            default => 'هذه الإجابة تحتاج تحديداً أكثر. حاول أن تكون أوضح من '.$minLength.' أحرف.',
        };
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, array<string, mixed>>  $upstreamContext
     */
    private function modeFocusNote(
        string $modeKey,
        Tool $tool,
        array $profile,
        ?Project $project,
        array $upstreamContext,
    ): string {
        $projectName = $project?->name ?? 'المشروع الحالي';
        $audience = trim((string) ($profile['audience'] ?? ''));
        $goal = trim((string) ($profile['primary_goal'] ?? ''));
        $goalDisplay = $this->displayPrimaryGoal($profile['primary_goal'] ?? null);
        $upstreamHeadline = trim((string) ($upstreamContext[0]['headline'] ?? ''));

        return match ($modeKey) {
            'guided' => 'املأ هذا الوضع بسرعة لكن بدقة. ابدأ بالمعلومة التي تغيّر القرار مباشرة في '.$projectName.'.',
            'structured' => 'هذا الوضع يحتاج إجابات أوضح تربط بين السبب والنتيجة والتنفيذ، لا مجرد عناوين.',
            'expert' => 'استخدم هذا الوضع لكتابة افتراضات أو مقايضات أو مخاطر تؤثر فعلاً على القرار التجاري.',
            default => 'اكتب إجابات عملية قابلة للاستخدام مباشرة.',
        }
        .($audience !== '' ? ' الجمهور المرجعي: '.$audience.'.' : '')
        .($goal !== '' ? ' الهدف الحالي: '.$goalDisplay.'.' : '')
        .($upstreamHeadline !== '' ? ' راجع أيضاً: '.$upstreamHeadline.'.' : '');
    }

    private function displayPrimaryGoal(?string $primaryGoal): string
    {
        $raw = trim((string) ($primaryGoal ?? ''));
        if ($raw === '') {
            return '';
        }

        return GoalCatalog::exists($raw) ? (string) GoalCatalog::label($raw) : $raw;
    }

    private function goalOutcomeContextHint(
        string $fieldLabel,
        string $projectLabel,
        string $goalRaw,
        string $goalDisplay,
        string $audience,
    ): string {
        $lead = $fieldLabel !== '' ? 'حول سؤال «'.$fieldLabel.'»' : 'هنا';

        if ($goalRaw !== '' && $audience !== '') {
            return $lead.' اكتب نتيجة واحدة محددة تخدم اتجاه مساحة عملك («'.$goalDisplay.'») ويمكن ربطها بسلوك حقيقي لجمهور '.$audience.'. تجنّب إعادة اسم الاتجاه فقط دون حدث أو رقم.';
        }

        if ($goalRaw !== '') {
            return $lead.' صِف حدثاً واحداً أو رقماً واحداً يمكن ملاحظته'.$projectLabel.'، في إطار اتجاه «'.$goalDisplay.'». لا يكفي تكرار اسم الاتجاه؛ اذكر ما الذي سيتحرك فعلياً.';
        }

        return $lead.' اذكر نتيجة واحدة قابلة للقياس أو الملاحظة خلال فترة محددة'.$projectLabel.'.';
    }

    private function goalRationaleContextHint(
        string $fieldLabel,
        string $projectLabel,
        string $goalDisplay,
        string $goalRaw,
    ): string {
        $lead = $fieldLabel !== '' ? 'في «'.$fieldLabel.'»' : 'هنا';

        if ($goalRaw !== '') {
            return $lead.' اشرح السبب التجاري: لماذا هذا الهدف يفتح الخطوة التالية'.$projectLabel.'؟ اربط الإجابة بقرار (وقت، مال، مخاطرة) وليس بعبارة عامة. اتجاه المساحة «'.$goalDisplay.'» هو خلفية فقط — لا تنسخه كإجابة.';
        }

        return $lead.' اشرح لماذا هذه الأولوية الآن وليست غيرها، وبأي شكل يقلل التخمين أو التشتت'.$projectLabel.'.';
    }

    private function measurableResultContextHint(
        string $fieldLabel,
        string $projectLabel,
        string $goalRaw,
        string $goalDisplay,
        string $audience,
    ): string {
        $lead = $fieldLabel !== '' ? 'للسؤال «'.$fieldLabel.'»' : 'هنا';

        if ($goalRaw !== '' && $audience !== '') {
            return $lead.' اذكر مخرجاً واحداً يمكن عدّه أو التحقق منه لجمهور '.$audience.'، ويخدم اتجاه «'.$goalDisplay.'» دون أن يكون إعادة لاسم الاتجاه فقط.';
        }

        if ($goalRaw !== '') {
            return $lead.' ركّز على معيار واحد واضح (رقم، حدث، موعد) في سياق «'.$goalDisplay.'»'.$projectLabel.'.';
        }

        return $lead.' اذكر نتيجة قابلة للقياس أو الملاحظة بوضوح'.$projectLabel.'.';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function smartGoalOutcomePlaceholder(
        array $field,
        string $original,
        ?Project $project,
        string $audience,
    ): string {
        if ($original !== '') {
            return $original;
        }

        $projectName = trim((string) ($project?->name ?? ''));

        if ($audience !== '' && $projectName !== '') {
            return 'مثال: في '.$projectName.' — أول استجابات جادة من '.$audience.' خلال 30–45 يوماً (عدد أو حدث واحد)';
        }

        if ($projectName !== '') {
            return 'مثال: في '.$projectName.' — نتيجة واحدة يمكن توثيقها خلال شهر (طلب، دفعة، موعد)';
        }

        return $audience !== ''
            ? 'مثال: لجمهور '.$audience.' — حدث واحد قابل للملاحظة خلال 30 يوماً'
            : 'مثال: نتيجة واحدة محددة خلال 30 يوماً يمكن معرفة إن تحققت';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function smartMeasurableResultPlaceholder(
        array $field,
        string $original,
        ?Project $project,
        string $audience,
    ): string {
        if ($original !== '') {
            return $original;
        }

        $projectName = trim((string) ($project?->name ?? ''));

        if ($audience !== '' && $projectName !== '') {
            return 'مثال: لـ '.$projectName.' — مؤشر واحد لـ '.$audience.' (طلبات، حجوزات، معدل رد…) خلال أسبوعين';
        }

        if ($projectName !== '') {
            return 'مثال: في '.$projectName.' — رقم أو حدث واحد يعني «نجاح» لهذا الأسبوع';
        }

        return $audience !== ''
            ? 'مثال: مؤشر واحد لجمهور '.$audience.' خلال فترة محددة'
            : 'مثال: نتيجة رقمية أو حدث واحد يمكن الرجوع إليه';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $profile
     */
    private function suggestGoalFieldSnippet(
        array $field,
        array $profile,
        ?Project $project,
        string $upstreamHeadline,
    ): ?string {
        $key = (string) ($field['key'] ?? '');
        $audience = trim((string) ($profile['audience'] ?? ''));
        $projectName = trim((string) ($project?->name ?? ''));
        $goalRaw = trim((string) ($profile['primary_goal'] ?? ''));

        $headlineKeys = ['goal_now', 'main_goal', 'biggest_gap', 'priority_week', 'primary_goal_text'];

        if ($upstreamHeadline !== '' && in_array($key, $headlineKeys, true)) {
            return Str::limit('بناءً على آخر ملخص: '.$upstreamHeadline, 200, '');
        }

        if ($audience !== '' && $projectName !== '') {
            return 'في '.$projectName.' لجمهور '.$audience.': نتيجة أولى قابلة للملاحظة خلال 30 يوماً (حدد رقماً أو حدثاً واحداً).';
        }

        if ($projectName !== '') {
            return 'لـ '.$projectName.': نتيجة واحدة قابلة للقياس خلال 30 يوماً بدل صياغة عامة.';
        }

        if ($audience !== '') {
            return 'لجمهور '.$audience.': نتيجة واحدة محددة خلال أسبوعين–30 يوماً يمكن التحقق منها.';
        }

        if ($goalRaw !== '' && ! GoalCatalog::exists($goalRaw) && in_array($key, ['goal_now', 'main_goal', 'primary_goal_text'], true)) {
            return $goalRaw;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $profile
     */
    private function suggestResultFieldSnippet(
        array $field,
        array $profile,
        ?Project $project,
        string $upstreamHeadline,
    ): ?string {
        $goalRaw = trim((string) ($profile['primary_goal'] ?? ''));

        if ($goalRaw !== '' && ! GoalCatalog::exists($goalRaw)) {
            return $goalRaw;
        }

        return $this->suggestGoalFieldSnippet($field, $profile, $project, $upstreamHeadline);
    }

    private function suggestGoalRationaleSnippet(?Project $project): ?string
    {
        $projectName = trim((string) ($project?->name ?? ''));

        if ($projectName !== '') {
            return 'لأن تحقيقه يقلل التخمين في '.$projectName.' ويُظهر مبكراً إن العرض يُقبل قبل مضاعفة الجهد أو الإنفاق.';
        }

        return 'لأن تحقيقه يثبت أن العرض مطلوب فعلاً قبل توسيع القنوات أو الإنفاق، ويعطيك معياراً واضحاً للمتابعة.';
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'أساسي الآن',
            'important' => 'مهم جداً',
            default => 'داعم',
        };
    }

    private function humanizeFieldKey(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return Str::of($key)
            ->replace('_', ' ')
            ->trim()
            ->toString();
    }
}
