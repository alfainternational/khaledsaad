<?php

namespace App\Application\Tooling;

use App\Domain\AI\Kernel\Agents\SpecialistReviewService;
use App\Domain\AI\Kernel\Gate\OutputQualityGate;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Services\AIService;
use App\Domain\AI\Services\QualityJudge;
use App\Domain\AI\Web\WebContextEnricher;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Dashboard\AwarenessCatalog;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PersonaCatalog;
use App\Support\Dashboard\StageCatalog;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BuildToolPayloadAction
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        private readonly ToolBlueprintCatalog $toolBlueprintCatalog,
        private readonly AIService $aiService,
        private readonly WebContextEnricher $webEnricher,
        private readonly KnowledgeStore $knowledge,
        private readonly SpecialistReviewService $specialistReview,
        private readonly OutputQualityGate $qualityGate,
        private readonly QualityJudge $qualityJudge,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function handle(
        Workspace $workspace,
        Project $project,
        Tool $tool,
        string $mode,
        array $inputs = [],
    ): array {
        $profile = $this->profileStore->get($workspace);
        $journeySnapshot = $this->journeyStore->getSnapshot($workspace, $project);
        $readiness = $this->journeyStore->getReadiness($workspace, $project);

        $clientName = $project->client?->name ?? 'العميل الحالي';
        $brief = trim((string) ($inputs['brief'] ?? ''));
        $filledInputsCount = collect($inputs)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->count();
        $personaLabel = PersonaCatalog::label($profile['persona'] ?? null);
        $awarenessLabel = AwarenessCatalog::label($profile['awareness_level'] ?? null);
        $goalLabel = GoalCatalog::label($profile['primary_goal'] ?? null);
        $stageLabel = StageCatalog::label((int) $tool->stage);
        $blueprint = $this->toolBlueprintCatalog->for($tool);

        $insights = array_values(array_filter([
            'الأداة الحالية تعمل داخل '.$stageLabel.' للمشروع '.$project->name.'.',
            'نوع المستخدم التشغيلي هو '.$personaLabel.' بمستوى فهم '.$awarenessLabel.'.',
            ! empty($profile['audience']) ? 'الجمهور الأساسي: '.$profile['audience'].'.' : null,
            ! empty($profile['country']) ? 'السوق/الدولة المرجعية للمحتوى: '.$profile['country'].'.' : null,
            'لهجة وأسلوب لغة المحتوى: '.ContentLocaleCatalog::label($profile['content_locale'] ?? null).'.',
            $goalLabel !== 'غير محدد' ? 'الهدف الحالي: '.$goalLabel.'.' : null,
            $clientName ? 'العميل المرتبط: '.$clientName.'.' : null,
            ! empty($journeySnapshot['current_step']) ? 'الخطوة الحالية في الرحلة: '.$journeySnapshot['current_step'].'.' : null,
        ]));

        // البحث الحيّ كوقود للتحليل: إشارات سوق حقيقية للأدوات المعتمدة على بيانات السوق.
        $webContext = $this->webEnricher->enrich($tool->code, $inputs, $profile, $project->name);
        if (is_array($webContext)) {
            $insights[] = 'إشارة سوق حيّة: '.$webContext['summary'];
            foreach (array_slice($webContext['findings'], 0, 2) as $finding) {
                $insights[] = 'من الإنترنت ['.$finding['category'].']: '.$finding['title'];
            }
        }

        // المعرفة المقطّرة (Distillation): playbook محلي للأداة يُغذّي التحليل بلا نداء LLM.
        $playbook = $this->knowledge->recall('playbook.'.$tool->code);
        $playbookData = is_array($playbook) ? ($playbook['data'] ?? null) : null;

        // حلقة التعليم (Teacher Loop): دروس استخلصها LLM في الخلفية من مخرجات النظام
        // المحلي السابقة — يستهلكها النظام المحلي الآن ليحسّن نفسه بلا نداء LLM.
        $teach = $this->knowledge->recall('teach.'.$tool->code);
        $teachData = is_array($teach) ? ($teach['data'] ?? null) : null;
        if (is_array($teachData) && ! empty($teachData['lessons'])) {
            foreach (array_slice((array) $teachData['lessons'], 0, 2) as $lesson) {
                if (is_string($lesson) && trim($lesson) !== '') {
                    $insights[] = 'درس متعلَّم: '.trim($lesson);
                }
            }
        }

        $toolSummary = $this->buildToolSummary($tool->code, $inputs, $project->name, $mode);

        $aiSummary = $this->tryAiSummary($workspace, $project, $tool, $inputs, array_filter([
            'workspace_profile' => $profile,
            'journey_snapshot' => $journeySnapshot,
            'readiness_snapshot' => $readiness,
            'web_signals' => $webContext,
            'playbook' => $playbookData,
            'lessons' => is_array($teachData) ? ($teachData['lessons'] ?? null) : null,
        ]));

        // بوابة الجودة على ملخّص LLM للأداة (كما في الاستوديو): لا نقبل صقلاً
        // لغوياً ضعيفاً/عاماً فنعرضه كتحليل. عند verdict=warn نُبقي النتيجة المحلية
        // القوية. يتدهور بأمان: بلا LLM ⇒ verdict=pass ⇒ سلوك كما كان.
        $isAiGenerated = false;
        if ($aiSummary) {
            $aiText = trim((string) ($aiSummary['headline'] ?? '').' '.(string) ($aiSummary['text'] ?? ''));
            $verdict = $this->qualityGate->assess(
                $tool->name ?: $tool->code,
                $aiText,
                (string) ($blueprint['outcome'] ?? ''),
            )['verdict'];

            if ($verdict !== 'warn') {
                $toolSummary = array_merge($toolSummary, $aiSummary);
                $isAiGenerated = true;
            }
        }

        $summary = [
            'headline' => $toolSummary['headline'] ?? ('مخرج '.$tool->name.' لمشروع '.$project->name),
            'text' => $toolSummary['text'] ?? 'تم توليد مخرج منظم يمكن البناء عليه في الخطوة التالية.',
            'stage_label' => $stageLabel,
            'persona_label' => $personaLabel,
            'awareness_label' => $awarenessLabel,
            'goal_label' => $goalLabel,
            'bullets' => $toolSummary['bullets'] ?? [],
            'ai_generated' => $isAiGenerated,
        ];

        $nextActions = $tool->next_actions_json ?: [
            'راجع الخلاصة وتأكد أنها تعكس واقع مشروعك فعلاً.',
            'انقل أهم نقطة منها إلى تنفيذ أو قرار قريب.',
            'انتقل بعدها إلى الأداة التالية في الرحلة.',
        ];

        $sourceContext = [
            'workspace_profile' => $profile,
            'journey_snapshot' => $journeySnapshot,
            'readiness_snapshot' => $readiness,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'stage' => $project->stage,
                'status' => $project->status,
            ],
            'client' => [
                'name' => $clientName,
            ],
            'tool_blueprint' => [
                'intro' => $blueprint['intro'] ?? null,
                'outcome' => $blueprint['outcome'] ?? null,
                'ai_role' => $blueprint['ai_role'] ?? null,
            ],
            'web_signals' => $webContext,
        ];

        $completenessScore = min(
            100,
            30
            + ($brief !== '' ? 10 : 0)
            + min(25, $filledInputsCount * 5)
            + (! empty($profile['audience']) ? 15 : 0)
            + (! empty($profile['primary_goal']) ? 15 : 0)
            + (! empty($journeySnapshot['current_step']) ? 10 : 0)
            + (! empty($readiness) ? 10 : 0)
        );

        // مراجعة الأخصائيين المحليين على إجابات المستخدم: صياغة عربية دائماً،
        // وقوة العرض في أدوات المرحلة الثالثة. محلي بالكامل، يتدهور بأمان.
        $specialistReview = $this->buildSpecialistReview($tool, $inputs);

        // جودة المحتوى (§8 ذكاء مضموني): تقييم *مضمون* الإجابات لا مجرد ملئها،
        // منفصلاً عن completeness_score. يتدهور بأمان (null بلا LLM).
        $contentQuality = $this->buildContentQuality($tool, $mode, $inputs);

        return [
            'output' => [
                'headline' => $summary['headline'],
                'summary' => $summary['text'],
                'insights' => array_values(array_filter([
                    ...($toolSummary['bullets'] ?? []),
                    ...$insights,
                ])),
                'next_actions' => $nextActions,
                'specialist_review' => $specialistReview,
                'content_quality' => $contentQuality,
                'brief' => $brief !== '' ? $brief : null,
                'inputs' => $inputs,
                'source_context' => $sourceContext,
            ],
            'summary' => $summary,
            'next_actions' => $nextActions,
            'source_context' => $sourceContext,
            'completeness_score' => $completenessScore,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function buildToolSummary(string $toolCode, array $inputs, string $projectName, string $mode): array
    {
        $dedicated = $this->dedicatedSummary($toolCode, $inputs, $projectName);

        return $dedicated ?? $this->genericSummary($toolCode, $inputs, $projectName, $mode);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>|null
     */
    private function dedicatedSummary(string $toolCode, array $inputs, string $projectName): ?array
    {
        $g = fn (string $key): string => trim((string) ($inputs[$key] ?? ''));
        $has = fn (string $key): bool => $g($key) !== '';

        return match ($toolCode) {
            'diagnosis' => $this->makeSummary(
                $has('main_goal') ? 'تشخيص: '.$g('main_goal') : 'تشخيص أولي لمشروع '.$projectName,
                $has('main_bottleneck')
                    ? 'العائق الرئيسي المحدد هو "'.$g('main_bottleneck').'" — يجب معالجته قبل أي شيء آخر.'
                    : 'لم يُحدد العائق الرئيسي بعد وهذا أول ما يحتاج تركيزاً.',
                array_filter([
                    $has('biggest_strength') ? 'استثمر قوتك في "'.$g('biggest_strength').'" لتعويض الفجوات' : null,
                    $has('biggest_gap') ? 'أولوية المعالجة: '.$g('biggest_gap').' — تأخيرها يكلّفك' : null,
                    $has('priority_week') ? 'ركّز هذا الأسبوع على: '.$g('priority_week') : 'حدد أولوية أسبوعية واحدة فقط لتبدأ',
                ]),
            ),
            'idea-clarity' => $this->makeSummary(
                $has('idea_name') ? 'فكرة: '.$g('idea_name') : 'اختبار وضوح الفكرة',
                $has('idea_problem') && $has('idea_audience')
                    ? 'فكرة تستهدف '.$g('idea_audience').' لحل "'.$g('idea_problem').'" — اختبرها قبل البناء الكامل.'
                    : 'الفكرة تحتاج تحديداً أوضح للمشكلة والشريحة المستهدفة قبل المضي.',
                array_filter([
                    $has('idea_value') ? 'القيمة المحورية: '.$g('idea_value').' — تأكد أن العميل يراها بنفس الطريقة' : null,
                    $has('idea_difference') ? 'ميزتك التنافسية: '.$g('idea_difference').' — هل يمكن إثباتها بدليل؟' : null,
                    'الخطوة التالية: اعرض الفكرة على 5 أشخاص من الشريحة المستهدفة واسأل: هل تدفع مقابل هذا؟',
                ]),
            ),
            'swot-analysis' => $this->makeSummary(
                'تحليل SWOT — '.$projectName,
                $has('strengths') && $has('opportunities')
                    ? 'الفرصة الهجومية: استغل "'.$g('strengths').'" لاقتناص "'.$g('opportunities').'" قبل المنافسين.'
                    : 'يجب تحديد نقاط القوة والفرص لبناء استراتيجية هجومية واضحة.',
                array_filter([
                    $has('weaknesses') && $has('threats')
                        ? 'تحذير: "'.$g('weaknesses').'" مع "'.$g('threats').'" = سيناريو خطير يجب معالجته فوراً'
                        : null,
                    $has('offensive_move') ? 'القرار الهجومي المحدد: '.$g('offensive_move').' — نفّذه خلال أسبوعين' : null,
                    $has('weaknesses') ? 'أول ضعف يجب إصلاحه: '.$g('weaknesses') : 'حدد أكبر نقطة ضعف وابدأ معالجتها',
                ]),
            ),
            'goal-definition' => $this->makeSummary(
                $has('goal_now') ? 'الهدف: '.$g('goal_now') : 'تحديد هدف '.$projectName,
                $has('goal_metric')
                    ? 'الهدف قابل للقياس عبر "'.$g('goal_metric').'" — راقب هذا المؤشر أسبوعياً.'
                    : 'الهدف يفتقر لمؤشر قياس واضح — بدونه لن تعرف هل تتقدم أم لا.',
                array_filter([
                    $has('goal_deadline') ? 'الموعد المحدد: '.$g('goal_deadline').' — ضع نقاط مراجعة كل أسبوع' : 'حدد موعداً نهائياً — الهدف بلا وقت مجرد أمنية',
                    $has('goal_obstacle') ? 'احذر من: '.$g('goal_obstacle').' — جهّز خطة بديلة مسبقاً' : null,
                    $has('goal_review_point') ? 'نقطة المراجعة: '.$g('goal_review_point') : 'حدد نقطة مراجعة أولى بعد أسبوع من البدء',
                ]),
            ),
            'problem-definition' => $this->makeSummary(
                $has('problem_core') ? 'المشكلة: '.$g('problem_core') : 'تحديد المشكلة الأساسية',
                $has('problem_root')
                    ? 'السبب الجذري المحدد هو "'.$g('problem_root').'" — ركّز هنا بدلاً من علاج الأعراض.'
                    : 'لم يُحدد السبب الجذري — قد تعالج أعراضاً بدلاً من المشكلة الحقيقية.',
                array_filter([
                    $has('problem_effect') ? 'تكلفة التأخير: '.$g('problem_effect').' — كل يوم بدون حل يضاعف الأثر' : null,
                    $has('problem_failed_solution') ? 'تعلّم من الفشل السابق: '.$g('problem_failed_solution').' — لا تكرر نفس الأسلوب' : null,
                    'اسأل 3 متأثرين مباشرة: كيف يصفون المشكلة بكلماتهم هم؟',
                ]),
            ),
            'tagline-builder' => $this->makeSummary(
                'الجملة التعريفية لـ'.$projectName,
                $has('who_help') && $has('end_result')
                    ? 'نساعد '.$g('who_help').' ليحصلوا على '.$g('end_result').' — اختبر هذه الصياغة كعنوان إعلان.'
                    : 'الجملة تحتاج تحديداً أوضح لـ"من تساعد" و"ما النتيجة" لتكون مقنعة.',
                array_filter([
                    $has('unique_angle') ? 'زاويتك المميزة: '.$g('unique_angle').' — أبرزها في كل محتواك' : 'تحتاج زاوية تميّزك عن المنافسين',
                    'اختبار سريع: اعرض الجملة على 5 أشخاص واسأل: ماذا فهمت؟ هل ستشتري؟',
                    $has('conversion_use') ? 'استخدمها في: '.$g('conversion_use') : 'ضعها في: Bio, إعلان, Landing page, توقيع الإيميل',
                ]),
            ),
            'ideal-customer' => $this->makeSummary(
                $has('customer_type') ? 'عميلك المثالي: '.$g('customer_type') : 'تحديد العميل المثالي',
                $has('customer_problem') && $has('buying_trigger')
                    ? 'عميلك يعاني من "'.$g('customer_problem').'" ويتحرك عندما "'.$g('buying_trigger').'" — خاطبه بهذه اللحظة.'
                    : 'حدد مشكلة عميلك ودافع شرائه بدقة لبناء رسالة تسويقية فعّالة.',
                array_filter([
                    $has('main_objection') ? 'اعتراضه الأول: "'.$g('main_objection').'" — جهّز ردّاً عليه في كل نقطة بيع' : null,
                    $has('best_channel') ? 'أفضل قناة للوصول إليه: '.$g('best_channel').' — ركّز ميزانيتك هنا' : 'حدد أين يقضي وقته بالضبط (القناة المحددة)',
                    'ابنِ رسالتك حول كلمات العميل نفسه وليس مصطلحاتك المهنية',
                ]),
            ),
            'positioning' => $this->makeSummary(
                'تمركز '.$projectName,
                $has('main_difference')
                    ? 'ميزتك التنافسية هي "'.$g('main_difference').'" — تأكد أن عميلك يراها ويفهمها بوضوح.'
                    : 'التمركز يحتاج ميزة واضحة يفهمها العميل في 10 ثوانٍ.',
                array_filter([
                    $has('market_gap') ? 'فجوة السوق التي تشغلها: '.$g('market_gap').' — احمِها من المنافسين' : null,
                    $has('proof_point') ? 'الدليل على تمركزك: '.$g('proof_point').' — استخدمه في كل محتواك' : 'تحتاج دليلاً ملموساً (عدد عملاء، نتيجة، شهادة)',
                    $has('positioning_statement') ? 'جملة التمركز: '.$g('positioning_statement') : null,
                ]),
            ),
            'market-analysis' => $this->makeSummary(
                'قراءة السوق — '.$projectName,
                $has('market_opportunity')
                    ? 'الفرصة الأوضح: "'.$g('market_opportunity').'" — ابدأ بالشريحة الأسهل وتوسّع تدريجياً.'
                    : 'حدد فرصة واحدة واضحة في السوق قبل الاستثمار.',
                array_filter([
                    $has('market_barrier') ? 'عائق يجب تجاوزه: '.$g('market_barrier').' — هل لديك حل عملي؟' : null,
                    $has('market_white_space') ? 'المساحة غير المشغولة: '.$g('market_white_space').' — هذه فرصتك الذهبية' : null,
                    $has('market_segment') ? 'ابدأ بشريحة '.$g('market_segment').' ثم توسّع بناءً على النتائج' : 'حدد شريحة واحدة محددة للبدء',
                ]),
            ),
            'competitor-analysis' => $this->makeSummary(
                'تحليل المنافسين — '.$projectName,
                $has('competitor_gap')
                    ? 'نقطة ضعف المنافس الأبرز: "'.$g('competitor_gap').'" — استغلها كميزة تنافسية.'
                    : 'حدد نقطة ضعف واضحة في المنافس يمكنك استغلالها.',
                array_filter([
                    $has('own_advantage') ? 'ميزتك: '.$g('own_advantage').' — ضاعف الاستثمار فيها' : null,
                    $has('white_space') ? 'المساحة الخالية: '.$g('white_space').' — ادخلها قبل أن يكتشفها المنافسون' : null,
                    $has('competitor_strength') ? 'ما يفعلونه أفضل: '.$g('competitor_strength').' — لا تنافس هنا، التفّ حوله' : null,
                ]),
            ),
            'offer-builder' => $this->makeSummary(
                $has('offer_name') ? 'العرض: '.$g('offer_name') : 'بناء العرض',
                $has('offer_result')
                    ? 'العرض يعد بـ"'.$g('offer_result').'" — تأكد أنه قابل للإثبات بدليل.'
                    : 'العرض يحتاج نتيجة واضحة يمكن قياسها وإثباتها.',
                array_filter([
                    $has('offer_guarantee') ? 'ضمانك: '.$g('offer_guarantee').' — هذا يقلل مخاطرة العميل ويسرّع القرار' : 'أضف ضمان (استرداد، نتيجة محددة) — بدونه العميل لن يخاطر',
                    $has('offer_audience') ? 'اختبر العرض على 10 من '.$g('offer_audience').' هذا الأسبوع' : null,
                    $has('offer_difference') ? 'تميّزك: '.$g('offer_difference').' — أبرزه في العنوان الرئيسي' : 'ما الذي يجعل عرضك مختلفاً عن المنافس؟',
                ]),
            ),
            'pricing-strategy' => $this->makeSummary(
                $has('pricing_offer') ? 'تسعير: '.$g('pricing_offer') : 'استراتيجية التسعير',
                $has('pricing_reason')
                    ? 'منطق التسعير: "'.$g('pricing_reason').'" — تأكد أن العميل يرى القيمة لا التكلفة.'
                    : 'التسعير يحتاج مبرراً واضحاً يقنع العميل أن السعر عادل.',
                array_filter([
                    $has('pricing_anchor') ? 'نقطة المقارنة: '.$g('pricing_anchor').' — اعرضها قبل السعر دائماً' : 'أنشئ نقطة مقارنة تجعل سعرك يبدو منطقياً',
                    $has('pricing_objection') ? 'جهّز رداً على: "'.$g('pricing_objection').'"' : null,
                    $has('pricing_floor') ? 'لا تنزل أبداً تحت: '.$g('pricing_floor') : 'حدد حداً أدنى لا تنزل تحته مهما حدث',
                ]),
            ),
            'value-ladder' => $this->makeSummary(
                'سلم القيمة — '.$projectName,
                $has('entry_offer')
                    ? 'المدخل "'.$g('entry_offer').'" يجب أن يُظهر القيمة بسرعة كافية لدفع العميل للمستوى التالي.'
                    : 'تحتاج عرضاً مدخلياً منخفض المخاطرة يُثبت قيمتك.',
                array_filter([
                    $has('core_offer') ? 'العرض الأساسي: '.$g('core_offer').' — هذا هو محرك الربح الرئيسي' : null,
                    $has('premium_offer') ? 'العرض المتقدم: '.$g('premium_offer').' — للعملاء الأوفياء فقط' : null,
                    $has('ladder_retention') ? 'آلية الاحتفاظ: '.$g('ladder_retention').' — حافظ على العميل بعد كل مستوى' : 'أضف آلية احتفاظ بين كل مستوى والتالي',
                ]),
            ),
            'package-builder' => $this->makeSummary(
                'الحزم — '.$projectName,
                $has('package_best_choice')
                    ? 'الحزمة الموصى بها: "'.$g('package_best_choice').'" — أبرزها بصرياً وسوّقها كالخيار الذكي.'
                    : 'حدد حزمة "موصى بها" واضحة — العميل يحتاج توجيهاً للاختيار.',
                array_filter([
                    $has('package_difference') ? 'الفرق بين الحزم: '.$g('package_difference').' — يجب أن يكون واضحاً من النظرة الأولى' : null,
                    $has('package_objection') ? 'اعتراض محتمل: "'.$g('package_objection').'" — عالجه في صفحة المقارنة' : null,
                    'نصيحة: اجعل الحزمة الوسطى الأكثر جاذبية (تأثير الوسطية)',
                ]),
            ),
            'promise-builder' => $this->makeSummary(
                'الوعد التسويقي — '.$projectName,
                $has('promise_result')
                    ? 'الوعد: "'.$g('promise_result').'" — هل يمكنك إثباته بشهادة أو رقم محدد؟'
                    : 'تحتاج وعداً محدداً وقابلاً للإثبات — الوعد الغامض لا يبيع.',
                array_filter([
                    $has('promise_proof') ? 'دليلك: '.$g('promise_proof').' — اعرضه بجانب الوعد دائماً' : 'تحتاج دليلاً (شهادة عميل، رقم، دراسة حالة)',
                    $has('promise_hook') ? 'الخطاف: "'.$g('promise_hook').'" — استخدمه كعنوان إعلان' : null,
                    $has('promise_limit') ? 'حدود واقعية: '.$g('promise_limit').' — الشفافية تبني الثقة' : 'أضف حدوداً واقعية — المبالغة تضر المصداقية',
                ]),
            ),
            'funnel-builder' => $this->makeSummary(
                'القمع التسويقي — '.$projectName,
                $has('funnel_blocker')
                    ? 'أكبر عائق في القمع: "'.$g('funnel_blocker').'" — عالجه أولاً قبل أي تحسين آخر.'
                    : 'حدد أين يتسرب أكبر عدد من العملاء في القمع.',
                array_filter([
                    $has('funnel_entry') ? 'نقطة الدخول: '.$g('funnel_entry').' — تأكد أنها تجذب الشريحة الصحيحة' : null,
                    $has('funnel_metric') ? 'المقياس الأهم: '.$g('funnel_metric').' — راقبه يومياً' : null,
                    $has('funnel_scaling') ? 'نقطة التوسع: عند تحقيق '.$g('funnel_scaling') : 'لا توسّع قبل تحقيق معدل تحويل مستقر',
                ]),
            ),
            'customer-journey' => $this->makeSummary(
                'رحلة العميل — '.$projectName,
                $has('journey_friction')
                    ? 'أكبر نقطة احتكاك: "'.$g('journey_friction').'" — أزلها أو خففها لتحسين التجربة.'
                    : 'حدد أكبر نقطة احتكاك في رحلة العميل — هذه أولويتك الأولى.',
                array_filter([
                    $has('journey_trust') ? 'لحظة بناء الثقة: '.$g('journey_trust').' — عزّزها بدليل اجتماعي' : null,
                    $has('journey_doubt') ? 'لحظة الشك: '.$g('journey_doubt').' — جهّز محتوى يعالجها مباشرة' : null,
                    $has('journey_retention') ? 'آلية الاحتفاظ: '.$g('journey_retention') : 'أضف آلية تجعل العميل الراضي يُحيل عملاء جدد',
                ]),
            ),
            'marketing-plan' => $this->makeSummary(
                $has('plan_goal') ? 'الخطة: '.$g('plan_goal') : 'الخطة التسويقية',
                $has('two_week_actions')
                    ? 'خطواتك القادمة: '.$g('two_week_actions').' — نفّذها خلال أسبوعين ثم قيّم النتائج.'
                    : 'حدد 3 مهام محددة تنفذها خلال أسبوعين — الخطة بلا جدول مجرد أمنيات.',
                array_filter([
                    $has('north_metric') ? 'مقياس النجاح: '.$g('north_metric').' — إذا لم يتحسن خلال أسبوعين، غيّر المسار' : null,
                    $has('plan_risks') ? 'مخاطر متوقعة: '.$g('plan_risks').' — جهّز خطة بديلة' : null,
                    $has('channel_primary') ? 'القناة الأساسية: '.$g('channel_primary').' — ركّز 80% من جهدك هنا' : 'اختر قناة واحدة وأتقنها قبل التوسع',
                ]),
            ),
            'content-plan' => $this->makeSummary(
                'خطة المحتوى — '.$projectName,
                $has('content_goal')
                    ? 'هدف المحتوى: "'.$g('content_goal').'" — كل قطعة يجب أن تخدم هذا الهدف مباشرة.'
                    : 'حدد هدفاً واحداً واضحاً للمحتوى — المحتوى بلا هدف مجرد ضوضاء.',
                array_filter([
                    $has('content_topics') ? 'المواضيع: '.$g('content_topics').' — رتّبها حسب مراحل القمع' : null,
                    $has('content_repurpose') ? 'إعادة الاستخدام: '.$g('content_repurpose').' — كل قطعة يجب أن تتحول لـ3 أشكال' : 'كل مقال يمكن أن يصبح: فيديو قصير + سلسلة تغريدات + إنفوجرافيك',
                    $has('content_funnel_fit') ? 'ربط بالقمع: '.$g('content_funnel_fit') : 'اربط كل محتوى بمرحلة محددة (وعي → اهتمام → قرار)',
                ]),
            ),
            'campaign-builder' => $this->makeSummary(
                'الحملة — '.$projectName,
                $has('campaign_goal')
                    ? 'هدف الحملة: "'.$g('campaign_goal').'" — حملة بهدف واحد تنجح أكثر من حملة بعدة أهداف.'
                    : 'حدد هدفاً واحداً للحملة — عدة أهداف = تشتت = فشل.',
                array_filter([
                    $has('campaign_test') ? 'عنصر الاختبار: '.$g('campaign_test').' — اختبره أولاً بميزانية صغيرة' : 'حدد عنصراً واحداً للاختبار (العنوان، الصورة، العرض)',
                    $has('campaign_risk') ? 'مخاطرة: '.$g('campaign_risk').' — ضع حداً أقصى للخسارة المقبولة' : null,
                    $has('campaign_channel') ? 'القناة: '.$g('campaign_channel') : 'اختر القناة التي يتواجد فيها عميلك أكثر',
                ]),
            ),
            'follow-up-sequence' => $this->makeSummary(
                'تسلسل المتابعة — '.$projectName,
                $has('followup_goal')
                    ? 'الهدف: "'.$g('followup_goal').'" — ابنِ الثقة تدريجياً قبل الطلب.'
                    : 'حدد هدف التسلسل — هل هو بيع مباشر أم بناء علاقة؟',
                array_filter([
                    $has('followup_channel') ? 'القناة: '.$g('followup_channel').' — تأكد أن عميلك يفضلها' : null,
                    $has('followup_stop') ? 'قاعدة التوقف: '.$g('followup_stop').' — لا تزعج من لا يريد' : 'ضع قاعدة توقف واضحة (مثلاً: بعد 3 رسائل بدون تفاعل)',
                    'النمط المثبت: رسالة قيمة → قصة نجاح → عرض → تذكير أخير',
                ]),
            ),
            'kpi-tracker' => $this->makeSummary(
                'مؤشرات الأداء — '.$projectName,
                $has('kpi_leading')
                    ? 'المؤشر القائد: "'.$g('kpi_leading').'" — إذا تحسّن هذا المؤشر ستتحسن كل النتائج.'
                    : 'حدد مؤشراً قائداً واحداً (يتنبأ بالمستقبل) بدلاً من مؤشرات تابعة (تصف الماضي).',
                array_filter([
                    $has('kpi_threshold') ? 'عتبة الإنذار: '.$g('kpi_threshold').' — عند تجاوزها تصرّف فوراً' : 'حدد عتبة إنذار — متى تعرف أن هناك مشكلة؟',
                    $has('kpi_action') ? 'إجراء الطوارئ: '.$g('kpi_action') : 'جهّز إجراء محدداً عند انخفاض المؤشر',
                    $has('kpi_owner') ? 'المسؤول: '.$g('kpi_owner') : 'حدد شخصاً واحداً مسؤولاً عن كل مؤشر',
                ]),
            ),
            'execution-plan' => $this->makeSummary(
                'الخطة التنفيذية — '.$projectName,
                $has('execution_first_step')
                    ? 'ابدأ بـ"'.$g('execution_first_step').'" — لا تنتقل للمهمة التالية حتى تنتهي من هذه.'
                    : 'حدد الخطوة الأولى بدقة — التنفيذ يبدأ من مهمة واحدة واضحة.',
                array_filter([
                    $has('execution_timeframe') ? 'الإطار الزمني: '.$g('execution_timeframe').' — ضع نقطة مراجعة في المنتصف' : null,
                    $has('execution_risk') ? 'مخاطرة: '.$g('execution_risk').' — جهّز بديلاً مسبقاً' : null,
                    $has('execution_dependencies') ? 'تبعيات: '.$g('execution_dependencies').' — أنجزها أولاً' : 'حدد: ما الذي يجب أن يتم قبل أن تبدأ؟',
                ]),
            ),
            'performance-review' => $this->makeSummary(
                'مراجعة الأداء — '.$projectName,
                $has('performance_pattern')
                    ? 'النمط المكتشف: "'.$g('performance_pattern').'" — هذا مفتاح تحسين الأداء.'
                    : 'ابحث عن النمط المتكرر في نتائجك — هل هناك شيء ينجح دائماً أو يفشل دائماً؟',
                array_filter([
                    $has('performance_win') ? 'ما نجح: '.$g('performance_win').' — ضاعفه' : null,
                    $has('performance_issue') ? 'ما تعثر: '.$g('performance_issue').' — أوقفه أو أصلحه' : null,
                    $has('performance_next') ? 'الإجراء التالي: '.$g('performance_next') : 'اتخذ قراراً واحداً بناءً على هذه البيانات — لا تؤجل',
                ]),
            ),
            'agency-audit' => $this->makeSummary(
                'تقييم الوكالة — '.$projectName,
                $has('agency_reported_results')
                    ? 'النتائج المرسلة من الوكالة: '.$g('agency_reported_results').' — لا تحكم عليها قبل ربطها بالهدف والتتبع والميزانية.'
                    : 'ابدأ من التقرير الفعلي للوكالة: ماذا صرفوا؟ ماذا وصل؟ وما الرقم الذي يثبت قيمة العمل؟',
                array_filter([
                    $has('agency_promise') ? 'الوعد المتفق عليه: '.$g('agency_promise').' — حوّله إلى مؤشر قياس واضح' : 'اطلب من الوكالة صياغة النتيجة المتوقعة كمؤشر لا ككلام عام',
                    $has('agency_tracking') ? 'طريقة القياس الحالية: '.$g('agency_tracking').' — تأكد أنها تقيس عميل/مبيعات لا تفاعل فقط' : 'اطلب منهم Pixel أو UTM أو طريقة إسناد واضحة قبل زيادة الميزانية',
                    $has('agency_concern') ? 'نقطة القلق: '.$g('agency_concern').' — اجعلها سؤالاً مباشراً في الاجتماع القادم' : null,
                    $has('agency_questions') ? 'أسئلة جاهزة للوكالة: '.$g('agency_questions') : 'اسأل: ما CAC؟ ما ROAS أو تكلفة الليد المؤهل؟ ما اختبار A/B القادم؟',
                    $has('agency_decision') ? 'قرارك الحالي: '.$g('agency_decision').' — لا تنفذه قبل مراجعة الدليل خلال فترة قياس قصيرة' : null,
                ]),
            ),
            'smart-recommendations' => $this->makeSummary(
                'التوصيات الذكية — '.$projectName,
                $has('recommendation_priority')
                    ? 'الأولوية القصوى: "'.$g('recommendation_priority').'" — ركّز كل مواردك هنا أولاً.'
                    : 'حدد أولوية واحدة واضحة — التشتت أكبر عدو النمو.',
                array_filter([
                    $has('recommendation_signal') ? 'الإشارة التي يجب مراقبتها: '.$g('recommendation_signal') : null,
                    $has('recommendation_resource') ? 'الموارد المطلوبة: '.$g('recommendation_resource').' — هل هي متاحة الآن؟' : null,
                    $has('recommendation_tradeoff') ? 'المقايضة: '.$g('recommendation_tradeoff').' — هل أنت مستعد لدفع هذا الثمن؟' : null,
                ]),
            ),
            'growth-priorities' => $this->makeSummary(
                'أولويات النمو — '.$projectName,
                $has('growth_option')
                    ? 'المسار المختار: "'.$g('growth_option').'" — '.($has('growth_reason') ? 'لأن '.$g('growth_reason').'.' : 'حدد لماذا هذا المسار وليس غيره.')
                    : 'حدد مسار نمو واحداً واضحاً — التشتت بين مسارات متعددة يبطئك.',
                array_filter([
                    $has('growth_first_move') ? 'الخطوة الأولى: '.$g('growth_first_move').' — نفّذها هذا الأسبوع' : null,
                    $has('growth_risk') ? 'مخاطرة: '.$g('growth_risk').' — حدد متى تتوقف' : null,
                    $has('growth_stop_rule') ? 'قاعدة التوقف: '.$g('growth_stop_rule') : 'حدد: متى تعرف أن هذا المسار لا يعمل؟',
                ]),
            ),
            default => null,
        };
    }

    /**
     * مراجعة الأخصائيين المحليين على إجابات المستخدم النصية.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{score: int|null, panels: array<int, array{key: string, name: string, score: int, items: array<int, string>}>}|null
     */
    private function buildSpecialistReview(Tool $tool, array $inputs): ?array
    {
        $reviewText = collect($inputs)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn ($value): string => trim((string) $value))
            ->implode(' • ');

        if ($reviewText === '') {
            return null;
        }

        $aspects = [SpecialistReviewService::ASPECT_LOCALIZATION];
        if ((int) $tool->stage === 3) {
            $aspects[] = SpecialistReviewService::ASPECT_OFFER;
        }

        $review = $this->specialistReview->review($reviewText, $aspects);

        return $review['panels'] === [] ? null : $review;
    }

    /**
     * درجة جودة *مضمون* الإجابات (لا الاكتمال) عبر QualityJudge. يتدهور بأمان:
     * يعيد null بلا LLM أو Kill Switch — فلا يُظهر جودة زائفة.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{score: int, note: string}|null
     */
    private function buildContentQuality(Tool $tool, string $mode, array $inputs): ?array
    {
        $blueprint = $this->toolBlueprintCatalog->for($tool);
        $labels = $this->fieldLabelsForMode($blueprint, $mode);

        $fields = [];
        foreach ($inputs as $key => $value) {
            if ($key === 'brief' || ! is_string($value) || trim($value) === '') {
                continue;
            }
            $fields[] = [
                'label' => (string) ($labels[$key] ?? $key),
                'value' => trim($value),
            ];
        }

        if ($fields === []) {
            return null;
        }

        return $this->qualityJudge->scoreInputs($tool->name ?: $tool->code, $fields);
    }

    /**
     * @param  array<string|null>  $rawBullets
     * @return array<string, mixed>
     */
    private function makeSummary(string $headline, string $text, array $rawBullets): array
    {
        return [
            'headline' => $headline,
            'text' => str_replace(['...', '.. '], ['غير محدد بعد', 'غير محدد بعد '], $text),
            'bullets' => array_values(array_filter($rawBullets)),
        ];
    }


    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function genericSummary(string $toolCode, array $inputs, string $projectName, string $mode): array
    {
        $blueprint = $this->toolBlueprintCatalog->for($toolCode);
        $fieldLabels = $this->fieldLabelsForMode($blueprint, $mode);
        $filledPairs = collect($inputs)
            ->except('brief')
            ->mapWithKeys(function ($value, $key) use ($fieldLabels): array {
                if (! is_string($value) || trim($value) === '') {
                    return [];
                }

                return [
                    $fieldLabels[$key] ?? $key => trim($value),
                ];
            })
            ->take(3);
        $firstValue = $filledPairs->first();
        $firstLabel = $filledPairs->keys()->first();
        $brief = trim((string) ($inputs['brief'] ?? ''));
        $resultLabel = $blueprint['result_label'] ?? 'النتيجة الحالية';
        $headlineSeed = is_string($firstValue) && $firstValue !== ''
            ? Str::limit($firstValue, 72)
            : $projectName;
        $textParts = array_values(array_filter([
            $firstLabel && $firstValue
                ? 'التركيز الحالي يدور حول '.$firstLabel.' وهو '.$firstValue.'.'
                : 'تم حفظ أساس هذه الأداة ويمكن الآن البناء عليه بشكل أوضح.',
            $brief !== ''
                ? 'أضفت أيضاً ملاحظة مهمة: '.Str::limit($brief, 120)
                : null,
        ]));

        return [
            'headline' => $resultLabel.': '.$headlineSeed,
            'text' => implode(' ', $textParts),
            'bullets' => $filledPairs
                ->map(fn (string $value, string $label): string => $label.': '.$value)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $sourceContext
     * @return array<string, mixed>|null
     */
    private function tryAiSummary(
        Workspace $workspace,
        Project $project,
        Tool $tool,
        array $inputs,
        array $sourceContext,
    ): ?array
    {
        $filledCount = collect($inputs)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->count();

        if ($filledCount < 2) {
            return null;
        }

        try {
            $raw = $this->aiService->generateSmartSummary(
                toolCode: $tool->code,
                toolName: $tool->name ?: $tool->code,
                inputs: $inputs,
                sourceContext: $sourceContext,
                workspaceId: $workspace->id,
                projectId: $project->id,
            );

            if (! $raw) {
                return null;
            }

            $cleaned = preg_replace('/^```json\s*/i', '', $raw);
            $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
            $decoded = json_decode(trim($cleaned), true);

            if (! is_array($decoded) || empty($decoded['headline'])) {
                return null;
            }

            return [
                'headline' => $decoded['headline'],
                'text' => $decoded['text'] ?? '',
                'bullets' => $decoded['bullets'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('AI summary generation failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return array<string, string>
     */
    private function fieldLabelsForMode(array $blueprint, string $mode): array
    {
        $labels = collect($blueprint['modes'] ?? [])
            ->flatMap(function (array $modeDefinition): array {
                return collect($modeDefinition['fields'] ?? [])
                    ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['label']])
                    ->all();
            })
            ->all();

        if (isset($blueprint['modes'][$mode]['fields'])) {
            return array_merge(
                $labels,
                collect($blueprint['modes'][$mode]['fields'])
                    ->mapWithKeys(fn (array $field): array => [$field['key'] => $field['label']])
                    ->all(),
            );
        }

        return $labels;
    }
}
