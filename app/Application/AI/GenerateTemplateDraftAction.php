<?php

namespace App\Application\AI;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\AI\Services\AiCreditService;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\AI\StudioTemplateContractRegistry;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\AI\StudioTemplateReadinessGate;
use App\Support\Dashboard\ContentLocaleCatalog;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Dashboard\PathCatalog;
use App\Support\AI\StudioOutputQualityGuard;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class GenerateTemplateDraftAction
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        private readonly AiGatewayInterface $aiGateway,
        private readonly StudioOutputQualityGuard $qualityGuard,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
        private readonly StudioTemplateContractRegistry $contractRegistry,
        private readonly StudioTemplateReadinessGate $readinessGate,
        private readonly AiCreditService $credits,
    ) {}

    /** تقدير توكن واقعي (الحرف العربي ≈ توكن لكل ~4 محارف) بدل عدّ الكلمات المضلّل. */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen(trim($text)) / 4);
    }

    public function handle(
        Workspace $workspace,
        AITemplate $template,
        ?Project $project,
        User $actor,
        ?string $brief = null,
    ): AIGeneration {
        $profile = $this->profileStore->get($workspace);
        $journeySnapshot = $project
            ? $this->journeyStore->getSnapshot($workspace, $project)
            : [];
        $readiness = $project
            ? $this->journeyStore->getReadiness($workspace, $project)
            : [];
        $generationContext = $this->contextBuilder->build($workspace, $project);
        $toolSummaries = $generationContext['tool_summaries'] ?? [];
        $generationContextBlock = $generationContext['prompt_block'] ?? '';
        $analysisDossier = $generationContext['analytical_dossier'] ?? [];
        $templateDefinition = $this->contractRegistry->definitionFor($template);
        $readinessAssessment = $this->readinessGate->assess($template, $workspace, $project, $generationContext);

        $contextHighlights = collect($toolSummaries)
            ->map(function (array $summary): string {
                return implode(' - ', array_filter([
                    $summary['headline'] ?? null,
                    $summary['text'] ?? null,
                ]));
            })
            ->filter()
            ->values()
            ->all();

        $localeKey = $profile['content_locale'] ?? 'ar_modern_fusha';
        if (! ContentLocaleCatalog::exists($localeKey)) {
            $localeKey = 'ar_modern_fusha';
        }
        $country = trim((string) ($profile['country'] ?? ''));

        $replacements = [
            '{{workspace_name}}' => $workspace->name,
            '{{project_name}}' => $project?->name ?? 'المشروع الحالي',
            '{{client_name}}' => $project?->client?->name ?? 'العميل الحالي',
            '{{primary_goal}}' => GoalCatalog::label($profile['primary_goal'] ?? null),
            '{{audience}}' => $profile['audience'] ?? 'غير محدد',
            '{{country}}' => $country !== '' ? $country : 'غير محدد',
            '{{content_locale}}' => ContentLocaleCatalog::label($localeKey),
            '{{brief}}' => $brief ?: 'بدون brief إضافي',
        ];

        $basePrompt = strtr($template->prompt_template, $replacements);

        $readinessLines = collect($readiness)
            ->map(fn (array $dimension): string => $dimension['label'].': '.$dimension['score'].'%')
            ->values()
            ->all();

        $outputContractBlock = $this->buildOutputContractBlock($template->output_contract_json, $templateDefinition);
        $systemPrompt = $this->qualityGuard->systemPrompt($template);

        if ($readinessAssessment['is_blocking'] === true) {
            $output = $this->buildMissingInputOutput(
                $template,
                $workspace,
                $project,
                $readinessAssessment,
                $analysisDossier,
            );

            return AIGeneration::query()->create([
                'account_id' => $workspace->account_id,
                'workspace_id' => $workspace->id,
                'project_id' => $project?->id,
                'template_id' => $template->id,
                'created_by' => $actor->id,
                'inputs_json' => [
                    'brief' => $brief,
                    'profile' => $profile,
                    'journey_snapshot' => $journeySnapshot,
                    'readiness_snapshot' => $readiness,
                    'tool_summaries' => $toolSummaries,
                    'analysis_dossier' => $analysisDossier,
                    'generation_context' => Arr::except($generationContext, ['prompt_block']),
                    'template_meta' => [
                        'code' => $template->code,
                        'domain' => $template->domain,
                        'output_contract' => $template->output_contract_json,
                        'registry_definition' => $templateDefinition,
                    ],
                    'readiness_assessment' => $readinessAssessment,
                ],
                'output' => $output,
                'tokens_used' => 0, // مسار نقص الإدخال: لا نداء LLM، لا استهلاك.
                'status' => 'needs_input',
            ]);
        }

        $aiPrompt = implode("\n\n", array_filter([
            '[هوية القالب]',
            'اسم القالب: '.$template->name,
            $template->domain ? 'المجال الفرعي: '.$template->domain : null,
            'نوع التسليم المطلوب: '.($templateDefinition['deliverable_label'] ?? 'ملف تنفيذي'),
            '[السياق]',
            'مساحة العمل: '.$workspace->name,
            'المشروع: '.($project?->name ?? 'غير مرتبط بمشروع'),
            'العميل: '.($project?->client?->name ?? 'غير محدد'),
            'الهدف: '.GoalCatalog::label($profile['primary_goal'] ?? null),
            'الجمهور: '.($profile['audience'] ?? 'غير محدد'),
            'الدولة أو السوق المرجعية للمحتوى (سوشيال، هبوط، إعلانات): '.($country !== '' ? $country : 'غير محدد — حدّثه من إعدادات الحساب'),
            'معرّف لهجة المحتوى من إعدادات الحساب (ملف المساحة): '.$localeKey,
            'اللهجة وأسلوب لغة النصوص التسويقية: '.ContentLocaleCatalog::label($localeKey).' — '.ContentLocaleCatalog::promptInstruction($localeKey),
            'المسار المقترح: '.PathCatalog::label($profile['recommended_path'] ?? null),
            'المرحلة الحالية: '.($journeySnapshot['current_stage'] ?? ($project?->stage ?? 'غير محدد')),
            'آخر نقطة في الرحلة: '.($journeySnapshot['current_step'] ?? 'غير محدد'),
            $readinessLines !== [] ? 'جاهزية المشروع: '.implode(' | ', $readinessLines) : null,
            $generationContextBlock !== '' ? $generationContextBlock : null,
            $outputContractBlock,
            $this->personaEmbodimentInstructions($template),
            '[المهمة التنفيذية]',
            $basePrompt,
            $brief ? 'ملاحظات المستخدم الإضافية: '.$brief : null,
            $this->executionDeliverableInstructions(),
            $this->concreteCopyRules(),
            $this->agencyHandoffStandard(),
            ...$this->contentLocaleMandatoryBlock($localeKey),
            'لا تستخدم رموز تعبيرية.',
            'لا تخترع أرقاماً أو شهادات أو نتائج؛ إن احتجت رقماً فاذكر أنه تقدير أو اطلب من المستخدم إدخاله.',
            'ميّز بين اليقين (من المدخلات) والفرضيات (وضعها تحت عنوان افتراضات).',
        ]));

        $generationResult = $this->generateHighQualityOutput($template, $aiPrompt, $systemPrompt);

        if (($generationResult['status'] ?? null) === 'needs_input') {
            $output = $this->buildQualityNeedsInputOutput(
                $template,
                $workspace,
                $project,
                $generationResult['issues'] ?? [],
                $analysisDossier,
            );

            return AIGeneration::query()->create([
                'account_id' => $workspace->account_id,
                'workspace_id' => $workspace->id,
                'project_id' => $project?->id,
                'template_id' => $template->id,
                'created_by' => $actor->id,
                'inputs_json' => [
                    'brief' => $brief,
                    'profile' => $profile,
                    'journey_snapshot' => $journeySnapshot,
                    'readiness_snapshot' => $readiness,
                    'tool_summaries' => $toolSummaries,
                    'analysis_dossier' => $analysisDossier,
                    'generation_context' => Arr::except($generationContext, ['prompt_block']),
                    'template_meta' => [
                        'code' => $template->code,
                        'domain' => $template->domain,
                        'output_contract' => $template->output_contract_json,
                        'registry_definition' => $templateDefinition,
                    ],
                    'readiness_assessment' => $readinessAssessment,
                    'quality_assessment' => [
                        'issues' => $generationResult['issues'] ?? [],
                        'candidate' => $generationResult['candidate'] ?? null,
                    ],
                ],
                'output' => $output,
                'tokens_used' => $this->estimateTokens($output),
                'status' => 'needs_input',
            ]);
        }

        $output = $generationResult['output'] ?? $this->buildFallbackOutput(
            $template,
            $project,
            $profile,
            $journeySnapshot,
            $readinessLines,
            $contextHighlights,
            $basePrompt,
        );

        $templateMeta = [
            'code' => $template->code,
            'domain' => $template->domain,
            'output_contract' => $template->output_contract_json,
        ];

        $generation = AIGeneration::query()->create([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'project_id' => $project?->id,
            'template_id' => $template->id,
            'created_by' => $actor->id,
            'inputs_json' => [
                'brief' => $brief,
                'profile' => $profile,
                'journey_snapshot' => $journeySnapshot,
                'readiness_snapshot' => $readiness,
                'tool_summaries' => $toolSummaries,
                'analysis_dossier' => $analysisDossier,
                'generation_context' => Arr::except($generationContext, ['prompt_block']),
                'template_meta' => $templateMeta,
                'readiness_assessment' => $readinessAssessment,
            ],
            'output' => $output,
            'tokens_used' => $this->estimateTokens($aiPrompt.' '.$output),
            'status' => 'completed',
        ]);

        // §31: كل توليد ناجح يستهلك رصيداً من دفتر الأرصدة. أفضل جهد — لا يكسر التسليم.
        $account = $workspace->account;
        if ($account !== null) {
            try {
                $this->credits->consume(
                    $account,
                    max(1, (int) $template->credit_cost),
                    'ai_studio.generation',
                    (string) ($generation->public_id ?? $generation->id),
                );
            } catch (\Throwable $e) {
                Log::warning('AI credit consume failed: '.$e->getMessage());
            }
        }

        return $generation;
    }

    /**
     * Instructs the model to adopt the mindset/voice of each marketing role for the relevant sections (copy vs design vs strategy vs sales).
     */
    private function personaEmbodimentInstructions(AITemplate $template): string
    {
        $code = is_string($template->code) ? trim($template->code) : '';

        $base = <<<'TXT'
[تقمص أدوار فريق التسويق — ملزم لكل استوديو]
اكتب كأنك **أعضاء فريق وكالة مختلفون** حسب نوع المقطع؛ **غيّر العقلية والأسلوب** بين الأقسام، ولا تكتب الملف كله بصوت «مستشار عام» واحد.

**قواعد عامة**
- **نصوص تسويقية وبيعية** (إعلان، منشور، هبوط، إيميل، واتساب، سكربت، شعارات، عناوين): تقمص **كاتب محتوى تسويقي / نسّاق إعلانات** محترف: إقناع، وضوح، CTA، لهجة المشروع، بدون لغة تقرير جافة.
- **مواصفات تصميم، مقاسات، إطار بصري، نص على الصورة، تسليم للمصمم**: تقمص **مصمم جرافيك / مبدع إعلان (Art director)**: دقة تقنية، هيكل مرئي، سلم ألوان أو اتجاه بصري عند الحاجة؛ **لا** تملأ هذا القسم بفقرات إقناع طويلة كالإعلان — بل توجيهات تنفيذية للمبدع.
- **استراتيجية براند، موضع، تميّز، تشخيص**: تقمص **استراتيجي براند / مستشار**: تحليل، قرارات، حدود، بدون زحف إلى نسخ إعلانية طويلة إلا حيث يُطلب «رسالة جاهزة» صراحة.
- **قياس، KPI، تحسين**: تقمص **محلل أداء تسويقي**: مؤشرات قابلة للقياس، منطق اختبار؛ لا تخترع أرقاماً.
- **مبيعات ومتابعة**: تقمص **مندوب مبيعات أو نجاح عميل**: لغة حوارية، خطوة تالية، احترام للاعتراضات.

TXT;

        $byCode = match ($code) {
            'social-ad' => "**هذا القالب:** الجسم الرئيسي للإعلانات = **مدير إعلانات (تفكير هدف/جمهور)** + **كاتب إعلانات**؛ قسم «توجيهات للمصمم» أو المبدع = **مبدع إعلان فقط**.\n",
            'landing-headlines' => "**هذا القالب:** عناوين ونصوص الهبوط = **كاتب تحويل (conversion copywriter)** و**كاتب UX**؛ ملاحظات المصمم = **مصمم واجهات / صفحات هبوط**.\n",
            'whatsapp-followup' => "**هذا القالب:** كامل التسلسل = **مندوب مبيعات أو مسؤول نجاح عميل** (ليس نبرة كاتب مقال).\n",
            'email-sequence' => "**هذا القالب:** الإيميلات = **كاتب بريد تسويقي / تحويل** (موضوع + جسم بسلسلة منطقية).\n",
            'content-calendar' => "**هذا القالب:** عمود النص = **كاتب محتوى ومسؤول سوشيال**؛ الهاشتاق والقناة = **مخطط محتوى**؛ عمود التسليم للمصمم = **مبدع بصري** عند الحاجة.\n",
            'sales-script' => "**هذا القالب:** الحوار والاعتراضات = **مندوب مبيعات أول** + **مدرب مبيعات** في هيكل المكالمة فقط.\n",
            'brand-diagnosis' => "**هذا القالب:** غالبية الملف = **استراتيجي براند**؛ أي مثال نصي قصير يوضح نقطة = **كاتب محتوى** يطبّق التشخيص.\n",
            'brand-positioning' => "**هذا القالب:** التحليل والموضع = **استراتيجي براند**؛ افصل بوضوح بين **Positioning الداخلي** و**Value Proposition** و**Elevator pitch** و**نسخة الموقع** و**رسالة البيع**؛ **الرسائل الجاهزة** = **كاتب محتوى استراتيجي**.\n",
            'brand-voice-guide' => "**هذا القالب:** القواعد والشخصية = **استراتيجي صوت**؛ أمثلة «هكذا تكتب / لا تكتب» = **كاتب محتوى** ينفّذ الدليل حرفياً.\n",
            'brand-full-pack' => "**هذا القالب:** أقسام متعددة — انتقل بين **استراتيجي براند** و**كاتب محتوى** حسب القسم (تشخيص/موضع/Framework وحدود مقابل نصوص جاهزة ورسائل بيع).\n",
            default => "**هذا القالب:** طبّق القواعد العامة أعلاه حسب عناوين الأقسام و`domain` القالب.\n",
        };

        return $base.$byCode;
    }

    /**
     * Applies to every studio template so outputs are handoff-ready execution packs.
     */
    private function executionDeliverableInstructions(): string
    {
        return '[مبدأ الملف التنفيذي]
المطلوب ملفاً يحتوي **مخرجات نصية فعلية** (قابلة للنسخ) وليس قائمة نوايا أو خططاً صياغية عامة.
- طبّق **[تقمص أدوار فريق التسويق]** أدناه: اكتب كل جزء من الملف **بعقلية المنفّذ المسؤول عنه** (كاتب محتوى، مصمم، مبيعات، استراتيجي…)، ولا تخلط أسلوب «محلل عام» مع نسخ الإعلان أو مواصفات التصميم.
- ابدأ بقسم «## المنفّذون المستهدفون»: إما **جدول قصير** (الدور ← ما الذي يستلمه من هذا الملف) بجملة واحدة لكل صف، أو **نقاط مسؤولية محددة** دون تكرار نفس الصياغة لكل دور. **ممنوع** ملء القسم بجمل «سوف ينفذ / سوف يركز / سوف يعمل» المتطابقة لكل منفّذ.
- كل قسم لاحق يجب أن يحتوي على **النص أو الجدول أو الحوار نفسه** الجاهز للاستخدام، وليس وصفاً لما سيتم كتابته لاحقاً.
- التزم بالدولة/السوق و**لهجة المحتوى المختارة في إعدادات الحساب** (نفس `content_locale` في السياق أعلاه) في كل نص منشور أو هبوط أو إعلان أو رسالة؛ لا تستبدلها بتخمين لهجة من «سياق العميل».
- إن خلاًّ في البيانات يمنع التنفيذ، اذكره تحت «## افتراضات أو بيانات ناقصة» ولا تخترع أرقاماً.';
    }

    /**
     * Anti-template rules: models often default to "سوف ي..." bullets; forbid that pattern.
     */
    private function concreteCopyRules(): string
    {
        return '[قواعد النصوص الجاهزة للنشر والإرسال والحديث]
1) **ممنوع** استخدام صيغ: «سوف ينفذ»، «سوف يركز»، «سوف يعمل على تحسين القناة أو التسعير»، أو أي جملة تكرر نفس الهيكل لكل قسم — هذا مرفوض.
2) **إلزامي:** كتابة **نصوص كاملة** يمكن للمستخدم لصقها مباشرة حيث ينطبق القالب:
   - إعلانات وسوشيال: **نسخ إعلانية كاملة** (عنوان + نص + وصف + CTA) بلهجة المشروع، وليس ملخصاً لما يجب أن يقوله الإعلان.
   - واتساب/متابعة: **نص كل رسالة** حرفياً بين علامات اقتباس أو في فقرة واضحة مرقّمة (رسالة 1، رسالة 2…).
   - إيميل: **موضوع + جسم كامل** لكل بريد جاهز للإرسال.
   - صفحة هبوط: **عناوين ونصوص فقرات وCTA** جاهزة للصق، وليس نقاطاً تقول «سوف تتضمن الصفحة…».
   - خطة محتوى: عمود النص/السكربت في الجدول يجب أن يحتوي **منشوراً كاملاً أو سكربتاً كاملاً** لذلك اليوم (عدة جمل على الأقل)، وليس «مقال عن…» أو «نص مستوحى من…».
   - سكربت بيع: **حوار تمثيلي** بأسطر مثل: «المندوب: …» / «العميل: …» أو فقرات متتابعة يُمكن قراءتها في مكالمة فوراً؛ وجدول الاعتراضات: **اعتراض حرفي** و**رد حرفي** جاهز.
3) قوائم التحقق والمؤشرات يمكن أن تكون نقاطاً، لكن **لا** تستبدل بها النصوص الإعلانية أو الحوارية المطلوبة أعلاه.
4) احترافية اللغة: دقة، وضوح، وملاءمة للجمهور والسوق دون حشو مكرر؛ **الالتزام بلهجة المحتوى من إعدادات المساحة إلزامي** (انظر [سياسات إلزامية — لغة ولهجة المحتوى من ملف المساحة]).
5) **إعلانات وسوشيال مدفوعة:** النص يُكتب كما يُلصق في مدير الإعلانات (مثل Meta) — **ممنوع** أن يكون مجرد اقتباس عميل بين علامتي تنصيص يصف رغبة عامة دون عرض واضح ودعوة للفعل وتميّز.
6) **واتساب/متابعة:** **ممنوع** استخدام حقل اسمه «عنوان» لرسالة واتساب؛ استخدم «رسالة 1، 2، 3…» أو جدولاً بأعمدة: الرسالة | النص الكامل الجاهز | التوقيت.';
    }

    /**
     * Quality bar for agency-grade handoff (addresses thin/repeated "studio" outputs).
     */
    private function agencyHandoffStandard(): string
    {
        return '[معيار تسليم الوكالة — ملزم للملفات التسويقية]
المطلوب مستوى **وكالة**: قابل للتسليم لمدير إعلانات أو منشّر **بعد تعديلات طفيفة** (اسم العلامة، رابط، أرقام من العميل) — وليس مسودة أفكار.

**مرفوض (لا تُخرِج مخرجات بهذا الشكل):**
- تكرار **نفس العنوان** أو وصف «حزمة…» أو اسم المشروع كخطاف في كل النسخ والمنشورات.
- نسختان A/B بفرق **كلمة أو جملة واحدة** فقط؛ يجب أن يختلف **الخطاف** (مشكلة مقابل نتيجة، سؤال مقابل إثبات، عاجل مقابل منطقي) لا الصياغة فقط.
- نصوص رقيقة تعيد صياغة «أريد تسويقاً لتوسيع مشروعي» دون **عرض** أو **سبب للثقة** أو **خطوة تالية** محددة حيث يسمح المدخل.
- ترك حقول تنفيذية فارغة: **منصّة الإعلان**، **هدف الحملة** (وعي/رسائل/تحويل…)، **جمهور مقترح**، **زر CTA**، **رابط أو UTM placeholder** — إن لم تُذكر في المدخلات فاذكر «يُحدَّد من العميل» مع **قيمة افتراضية مقترحة** صالحة للتجربة.
- وصف إعلان = **وصف إعلان فعلي** أو «لا يُستخدم في هذا الشكل» وليس تكرار عنوان الحزمة.

**إلزامي لقوالب الإعلانات والسوشيال المدفوعة (عند انطباق القالب):**
- غطّ **منصّة واحدة على الأقل بعمق** (غالباً Meta: فيسبوك/إنستغرام): لكل نسخة إعلانية جدول أو حقول واضحة: **النص الأساسي (طويل)** + **العنوان** + **الوصف** (إن وُجد) + **زر CTA** + **ملاحظات للصورة/الفيديو** (زاوية، مشهد، نص على الإبداع).
- **ثلاث نسخ كاملات على الأقل** إجمالاً في الملف (يمكن توزيعها على منصّة أو مرحلتين قمع)، كلها **متميزة** لا نسخاً مكررة.
- قسم **توجيهات للمصمم**: مقاسات شائعة (مربع 1:1، عمودي 4:5، ستوري 9:16) وما يجب أن يظهر في الإطار.

**إلزامي لتسلسل واتساب:** جدول برقم الرسالة والنص الكامل والتوقيت؛ **بدون** عمود عنوان للرسالة.';
    }

    /**
     * Binding copy language/dialect from workspace profile (`business.profile.content_locale`).
     * Replaces a generic «فصحى أو دارجة حسب السياق» rule that would override the user's choice.
     *
     * @return list<string>
     */
    private function contentLocaleMandatoryBlock(string $localeKey): array
    {
        $label = ContentLocaleCatalog::label($localeKey);
        $instruction = ContentLocaleCatalog::promptInstruction($localeKey);

        $lines = [
            '[سياسات إلزامية — لغة ولهجة المحتوى من ملف المساحة]',
            'المستخدم اختار لهجة المحتوى في إعدادات الحساب (ملف المساحة): «'.$label.'». '.$instruction,
        ];

        if ($localeKey === 'en') {
            $lines[] = 'اكتب **جميع** النصوص التسويقية القابلة للنشر (إعلان، منشور، بريد، واتساب، سكربت، عناوين وهبوط) بالإنجليزية، باستثناء الأسماء الخاصة أو المصطلحات المطلوبة كما هي.';
        } elseif ($localeKey === 'ar_en_mixed') {
            $lines[] = 'طبّق خيار العربي/الإنجليزي المختلط كما في وصف الكتالوج أعلاه؛ لا تُحوّل المخرج تلقائياً إلى فصحى فقط أو إلى إنجليزي كامل دون داعٍ.';
        } else {
            $lines[] = 'هذه اللهجة **ملزمة** لكل نص منشور أو إعلان أو رسالة أو حوار في الملف؛ لا تستبدلها بفصحى عامة أو بلهجة منطقة أخرى لأنك «تخمّن» سياق العميل.';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>|null  $contract
     */
    private function buildOutputContractBlock(?array $contract, array $definition = []): ?string
    {
        if ($contract === null || $contract === []) {
            return null;
        }

        $sections = $contract['sections'] ?? [];
        $rubric = isset($contract['quality_rubric']) ? trim((string) $contract['quality_rubric']) : '';
        $definitionOfDone = collect($definition['definition_of_done'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $strategicRequirements = collect($definition['strategic_requirements'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
        $commodityWarnings = collect($definition['generic_red_flags'] ?? [])
            ->map(fn (string $line): string => '- تجنب كتابة أو اعتماد: '.$line)
            ->implode("\n");
        $forbidden = collect($definition['forbidden_fragments'] ?? [])
            ->map(fn (string $line): string => '- ممنوع ظهور: '.$line)
            ->implode("\n");

        $lines = [
            '[هيكل المخرج الإلزامي]',
            'التزم بكتابة ملف تنفيذي كامل: **نصوص جاهزة للنسخ** في الأقسام المناسبة، بلغة ولهجة المحتوى المحددة في سياق المساحة (content_locale) — وليس أوصافاً مستقبلية. ابدأ كل قسم رئيسي بعنوان مستقل بصيغة Markdown: ## عنوان القسم',
            'يجب أن يتضمن المخرج قسماً ختامياً بعنوان يعادل: «كيف تقيس النجاح وما تعدّله إن لم تتحقق الأهداف» (يمكن اختصار العنوان مع الإبقاء على المعنى).',
            'إن لم يكن «المنفّذون المستهدفون» ضمن الأقسام أدناه صراحة، أضفه كقسم أول وفق تعليمات [مبدأ الملف التنفيذي] — دون حشوه بصيغ «سوف…» المكررة.',
            'طبّق [قواعد النصوص الجاهزة للنشر والإرسال والحديث] على كل قالب.',
            $definitionOfDone !== '' ? "تعريف الإنجاز لهذا القالب:\n".$definitionOfDone : null,
            $strategicRequirements !== '' ? "متطلبات التفكير الاستراتيجي لهذا القالب:\n".$strategicRequirements : null,
            $commodityWarnings !== '' ? "صياغات Commodity أو أمثلة مرفوضة يجب تجنبها:\n".$commodityWarnings : null,
            $forbidden !== '' ? "ممنوعات هذا القالب:\n".$forbidden : null,
        ];

        foreach ($sections as $title) {
            $title = trim((string) $title);
            if ($title !== '') {
                $lines[] = '- يجب أن يشمل المخرج قسماً يغطي: «'.$title.'».';
            }
        }

        if ($rubric !== '') {
            $lines[] = 'معايير جودة إضافية: '.$rubric;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $journeySnapshot
     * @param  list<string>  $readinessLines
     * @param  list<string>  $contextHighlights
     */
    private function buildFallbackOutput(
        AITemplate $template,
        ?Project $project,
        array $profile,
        array $journeySnapshot,
        array $readinessLines,
        array $contextHighlights,
        string $basePrompt,
    ): string {
        return implode("\n\n", [
            '## ملخص تنفيذي',
            'لم يتوفر اتصال بالذكاء الاصطناعي أو تعذر التوليد. فيما يلي مسودة منظمة من بيانات المشروع يمكنك نسخها وتطويرها يدوياً.',
            '## السياق',
            'عنوان القالب: '.$template->name,
            'المشروع: '.($project?->name ?? 'غير مرتبط بمشروع'),
            'العميل: '.($project?->client?->name ?? 'غير مرتبط بعميل'),
            'الدولة/السوق: '.(trim((string) ($profile['country'] ?? '')) !== '' ? trim((string) $profile['country']) : 'غير محدد'),
            'لهجة المحتوى: '.ContentLocaleCatalog::label($profile['content_locale'] ?? 'ar_modern_fusha'),
            'الهدف: '.GoalCatalog::label($profile['primary_goal'] ?? null),
            'الجمهور: '.($profile['audience'] ?? 'غير محدد'),
            'المسار: '.PathCatalog::label($profile['recommended_path'] ?? null),
            'المرحلة الحالية: '.($journeySnapshot['current_stage'] ?? ($project?->stage ?? 'غير محدد')),
            'آخر نقطة في الرحلة: '.($journeySnapshot['current_step'] ?? 'غير محدد'),
            '## جاهزية المشروع',
            $readinessLines !== [] ? implode(' | ', $readinessLines) : 'لا توجد قراءة جاهزية بعد.',
            '## أهم الملخصات من أدوات الرحلة',
            $contextHighlights !== [] ? implode("\n", $contextHighlights) : 'لا توجد مخرجات أدوات محفوظة بعد.',
            '## المطلوب تنفيذياً',
            $basePrompt,
            '## خطوات تالية مقترحة',
            '1. راجع النص بما يناسب البراند والعميل.',
            '2. حوّل المسودة إلى مهام تنفيذية أو وثائق داخلية.',
            '3. احفظ النسخة النهائية واربطها بمؤشرات قياس واضحة.',
        ]);
    }

    /**
     * @return array{status: string, output?: string|null, issues?: list<string>, candidate?: string|null}
     */
    private function generateHighQualityOutput(
        AITemplate $template,
        string $prompt,
        string $systemPrompt,
    ): array {
        $firstAttempt = $this->requestAiText($prompt, $systemPrompt);
        if ($firstAttempt === null) {
            return ['status' => 'unavailable', 'output' => null];
        }

        $issues = $this->qualityGuard->issuesFor($firstAttempt, $template->output_contract_json, $template);
        if ($issues === []) {
            return ['status' => 'completed', 'output' => $firstAttempt];
        }

        Log::info('AI Studio output flagged as weak, requesting rewrite.', [
            'template' => $template->code,
            'issues' => $issues,
        ]);

        $revisedAttempt = $this->requestAiText(
            $this->qualityGuard->revisionPrompt($prompt, $firstAttempt, $issues, $template),
            $systemPrompt,
        );

        if ($revisedAttempt === null) {
            if ($this->shouldEscalateQualityFailureToNeedsInput($template, $issues)) {
                return [
                    'status' => 'needs_input',
                    'issues' => $issues,
                    'candidate' => $firstAttempt,
                ];
            }

            return ['status' => 'completed', 'output' => $firstAttempt];
        }

        $revisedIssues = $this->qualityGuard->issuesFor($revisedAttempt, $template->output_contract_json, $template);

        if ($revisedIssues === []) {
            return ['status' => 'completed', 'output' => $revisedAttempt];
        }

        $candidate = count($revisedIssues) <= count($issues) ? $revisedAttempt : $firstAttempt;
        $candidateIssues = count($revisedIssues) <= count($issues) ? $revisedIssues : $issues;

        if ($this->shouldEscalateQualityFailureToNeedsInput($template, $candidateIssues)) {
            return [
                'status' => 'needs_input',
                'issues' => $candidateIssues,
                'candidate' => $candidate,
            ];
        }

        return [
            'status' => 'completed',
            'output' => $this->buildQualityFailureOutput($template, $candidate, $candidateIssues),
        ];
    }

    private function requestAiText(string $prompt, string $systemPrompt): ?string
    {
        try {
            $output = $this->aiGateway->generateText($prompt, $systemPrompt);

            return is_string($output) && trim($output) !== '' ? trim($output) : null;
        } catch (\Throwable $e) {
            Log::warning('AI Studio generation failed, falling back to template: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @param  array<string, mixed>  $analysisDossier
     */
    private function buildMissingInputOutput(
        AITemplate $template,
        Workspace $workspace,
        ?Project $project,
        array $assessment,
        array $analysisDossier,
    ): string {
        $missingLines = collect($assessment['missing'] ?? [])
            ->map(fn (array $item): string => '- '.$item['label']."\n  السبب: ".$item['reason'])
            ->implode("\n");

        $questions = collect($assessment['questions'] ?? [])
            ->map(fn (string $question, int $index): string => ($index + 1).'. '.$question)
            ->implode("\n");

        $guide = trim((string) ($analysisDossier['guide_markdown'] ?? ''));

        return implode("\n\n", array_filter([
            '## لم يتم إنتاج ملف نهائي بعد',
            'هذا القالب يحتاج بيانات إضافية قبل أن يصبح المخرج قابلاً للتطبيق. تم إيقاف التوليد النهائي بدلاً من تسليم ملف ضعيف أو عام.',
            '## القالب المطلوب',
            $template->name.' — '.($assessment['definition']['deliverable_label'] ?? 'ملف تنفيذي'),
            '## ما البيانات الناقصة أو الضعيفة',
            $missingLines !== '' ? $missingLines : '- لا توجد بيانات ناقصة محددة، لكن السياق ما زال غير كافٍ.',
            '## الأسئلة التي يجب حسمها',
            $questions !== '' ? $questions : '1. ما الهدف الدقيق لهذا الملف؟',
            '## ما الذي نعرفه حالياً',
            implode("\n", array_filter([
                '- المساحة: '.$workspace->name,
                $project ? '- المشروع: '.$project->name : null,
                $project?->client ? '- العميل: '.$project->client->name : null,
            ])),
            $guide !== '' ? $guide : null,
            '## الخطوة التالية',
            'أجب عن الأسئلة أعلاه أو أكمل الأدوات/الملاحظات المرتبطة، ثم أعد التوليد لنخرج بملف نهائي صالح للتسليم.',
        ]));
    }

    /**
     * @param  list<string>  $issues
     */
    private function buildQualityFailureOutput(AITemplate $template, string $candidate, array $issues): string
    {
        $definition = $this->contractRegistry->definitionFor($template);
        $definitionOfDone = collect($definition['definition_of_done'] ?? [])
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");

        return implode("\n\n", array_filter([
            '## تم رفض المخرج قبل التسليم',
            'النص الذي عاد من النموذج لم يحقق تعريف الإنجاز لهذا القالب، لذلك لم يتم اعتماده كمخرج نهائي.',
            '## أسباب الرفض',
            '- '.implode("\n- ", $issues),
            $definitionOfDone !== '' ? "## تعريف الإنجاز المطلوب\n".$definitionOfDone : null,
            '## المسودة المرفوضة للمراجعة',
            $candidate,
            '## الإجراء المطلوب',
            'راجع المدخلات أو زوّد السياق ببيانات أوضح، ثم أعد التوليد. الهدف هنا منع حفظ ملف مضلل أو غير قابل للتطبيق.',
        ]));
    }

    /**
     * @param  list<string>  $issues
     * @param  array<string, mixed>  $analysisDossier
     */
    private function buildQualityNeedsInputOutput(
        AITemplate $template,
        Workspace $workspace,
        ?Project $project,
        array $issues,
        array $analysisDossier,
    ): string {
        $definition = $this->contractRegistry->definitionFor($template);
        $questions = $this->qualityFailureQuestions($definition, $issues);
        $guide = trim((string) ($analysisDossier['guide_markdown'] ?? ''));

        return implode("\n\n", array_filter([
            '## لم يتم اعتماد الملف النهائي بعد',
            'تم إيقاف اعتماد هذا القالب لأن المادة الاستراتيجية الحالية لا تكفي لإخراج ملف قابل للدفاع أو التطبيق. بدلاً من حفظ ملف مضلل، تحولت العملية إلى `needs_input` حتى تُستكمل الإشارات الناقصة أولاً.',
            '## لماذا أوقفنا التوليد النهائي',
            '- '.implode("\n- ", $issues),
            '## القالب المطلوب',
            $template->name.' — '.($definition['deliverable_label'] ?? 'ملف تنفيذي'),
            '## الأسئلة التي يجب حسمها قبل إعادة التوليد',
            collect($questions)
                ->values()
                ->map(fn (string $question, int $index): string => ($index + 1).'. '.$question)
                ->implode("\n"),
            '## ما الذي نعرفه حالياً',
            implode("\n", array_filter([
                '- المساحة: '.$workspace->name,
                $project ? '- المشروع: '.$project->name : null,
                $project?->client ? '- العميل: '.$project->client->name : null,
            ])),
            $guide !== '' ? $guide : null,
            '## الخطوة التالية',
            'أجب عن الأسئلة أعلاه أو أكمل الأدوات المرتبطة بالتموضع/العرض/التشخيص، ثم أعد التوليد ليخرج الملف النهائي على مادة قرار حقيقية لا على تخمينات شكلية.',
        ]));
    }

    /**
     * @param  list<string>  $issues
     */
    private function shouldEscalateQualityFailureToNeedsInput(AITemplate $template, array $issues): bool
    {
        $definition = $this->contractRegistry->definitionFor($template);
        $markers = collect($definition['quality_needs_input_issue_markers'] ?? [])
            ->filter(fn (mixed $marker): bool => is_string($marker) && trim($marker) !== '')
            ->values()
            ->all();

        if ($markers === []) {
            return false;
        }

        foreach ($issues as $issue) {
            foreach ($markers as $marker) {
                if (mb_strpos($issue, $marker) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  list<string>  $issues
     * @return list<string>
     */
    private function qualityFailureQuestions(array $definition, array $issues): array
    {
        $questions = collect($definition['missing_questions'] ?? [])
            ->filter(fn (mixed $question): bool => is_string($question) && trim($question) !== '')
            ->values();

        foreach ($issues as $issue) {
            if (str_contains($issue, 'Segment وMoment وUnique Mechanism') || str_contains($issue, 'المشاريع الصغيرة')) {
                $questions->push('من هي الشريحة الأضيق التي نستهدفها الآن تحديداً، وما المرحلة أو الظرف الذي يمر به هذا العميل عندما يبحث عن حلنا؟');
            }

            if (str_contains($issue, 'Value Proposition') || str_contains($issue, 'Commodity')) {
                $questions->push('ما التحول الملموس أو النتيجة التي نستطيع الوعد بها بشكل صادق وقابل للفهم، بدلاً من عبارات عامة مثل تحسين الحضور أو حلول عملية؟');
            }

            if (str_contains($issue, 'أسباب الثقة')) {
                $questions->push('ما الدليل الحقيقي أو المنطق التشغيلي أو سابقة العمل التي تبرر اختيارنا دون مبالغة؟');
            }

            if (str_contains($issue, 'يعيد نفس الرسالة') || str_contains($issue, 'أصول الرسائل')) {
                $questions->push('كيف تختلف وظيفة كل أصل رسائلي عندنا: التموضع الداخلي، نسخة الموقع، ورسالة البيع الافتتاحية، وما الذي يجب أن يقوله كل واحد تحديداً؟');
            }

            if (str_contains($issue, 'Framework') || str_contains($issue, '30-60-90') || str_contains($issue, '90 يوماً')) {
                $questions->push('ما المراحل التشغيلية الفعلية التي نمر بها من التشخيص إلى الرسالة إلى الاختبار، وما المخرج المتوقع من كل مرحلة؟');
            }

            if (str_contains($issue, 'Boundary')) {
                $questions->push('من هو العميل أو نوع الطلب الذي يجب أن نرفضه بوضوح، وما الذي لا نريد أن يرتبط بنا في السوق؟');
            }

            if (str_contains($issue, 'خريطة الجذور') || str_contains($issue, 'أولوية الإصلاح الأولى')) {
                $questions->push('ما الجذر الأكثر تسبباً في المشكلة الآن: الرسالة أم العرض أم الثقة أم القناة أم التجربة أم القياس، ولماذا؟');
            }
        }

        return $questions
            ->unique()
            ->values()
            ->all();
    }
}
