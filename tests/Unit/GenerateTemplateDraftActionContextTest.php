<?php

namespace Tests\Unit;

use App\Application\AI\GenerateTemplateDraftAction;
use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Account\Models\Account;
use App\Domain\Approval\Models\Approval;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\AI\StudioAnalyticalDossierBuilder;
use App\Support\AI\StudioOutputQualityGuard;
use App\Support\AI\StudioTemplateContractRegistry;
use App\Support\AI\StudioTemplateReadinessGate;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateTemplateDraftActionContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function studio_generation_includes_all_aggregated_notes_and_tool_context_before_writing(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'project' => $project, 'toolRun' => $toolRun] = $this->makeWorkspaceScenario();

        $template = AITemplate::query()->create([
            'code' => 'brand-positioning',
            'name' => 'Brand Positioning',
            'description' => 'Creates a positioning pack.',
            'prompt_template' => 'ابنِ ملف تمركز لمشروع {{project_name}} يستهدف {{audience}}.',
            'model' => 'gpt-5',
            'credit_cost' => 0,
            'status' => 'published',
            'system_role' => 'أنت استراتيجي براند يكتب ملفات تنفيذية.',
            'output_contract_json' => [
                'sections' => ['المنفذون المستهدفون', 'التمركز', 'الرسائل', 'القياس'],
            ],
        ]);

        $gateway = new class implements AiGatewayInterface
        {
            /** @var array<int, array{prompt: string, system: string|null}> */
            public array $calls = [];

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                $this->calls[] = [
                    'prompt' => $prompt,
                    'system' => $systemPrompt,
                ];

                if (str_contains($prompt, '"client_personality_summary"')) {
                    return json_encode([
                        'client_personality_summary' => 'عميل يفضل خطاباً خليجياً منضبطاً يركز على الثقة والنتيجة.',
                        'voice_and_tone' => [
                            'dialect' => 'لهجة خليجية (مبسطة للمحتوى)',
                            'register' => 'مهني مباشر',
                            'style' => 'مختصر وواضح',
                            'pace' => 'متوسط',
                            'persuasion_style' => 'ابدأ بالنتيجة ثم الدليل',
                        ],
                        'decision_drivers' => [
                            'trust_builders' => ['إثبات النتيجة', 'تخفيف المخاطرة'],
                            'decision_triggers' => ['رسائل واضحة', 'فائدة قابلة للقياس'],
                            'objections_or_fears' => ['الوعود الفضفاضة'],
                            'aversion_triggers' => ['المبالغة', 'اللغة العامة'],
                        ],
                        'content_preferences' => [
                            'preferred_angles' => ['وضوح الفائدة', 'النتيجة العملية'],
                            'preferred_patterns' => ['جمل قصيرة', 'اعتراض ثم رد'],
                            'avoided_patterns' => ['الحشو', 'التضخيم'],
                        ],
                        'brand_dictionary' => [
                            'preferred_keywords' => ['حجوزات', 'وضوح', 'ثقة'],
                            'preferred_phrases' => ['نتيجة يمكن ملاحظتها', 'رسالة واضحة تقود لقرار'],
                            'phrases_to_avoid' => ['أفضل من الجميع'],
                            'cta_patterns' => ['ابدأ بمراجعة واضحة'],
                        ],
                        'execution_rules' => ['ابدأ بالنتيجة', 'اربط كل وعد بدليل'],
                        'strategic_summary' => 'الكتابة هنا يجب أن تقود إلى قرار مطمئن لا إلى انبهار مؤقت.',
                    ], JSON_UNESCAPED_UNICODE);
                }

                return <<<'TXT'
## المنفذون المستهدفون
| الدور | ما الذي يستلمه |
| --- | --- |
| مدير الحساب | قرار التموضع وحدود البراند والفرضيات التي تحتاج تحققاً |
| كاتب المحتوى | Elevator pitch ونسخة الموقع ورسالة البيع الافتتاحية |
| مدير الإعلانات | Segment واضح وخطاف بيع وسبب ثقة أولي للاختبار |

## ملخص القرار الاستراتيجي
القرار هو تضييق البراند على عيادات الأسنان التي بدأت تصرف على التسويق لكن رسائلها الحالية لا تحول الاهتمام إلى حجوزات مؤكدة. الأولوية ليست "زيادة الظهور" بل بناء رسالة مطمئنة ومباشرة تربط الإعلان بالحجز المؤهل.

## Segment + Moment + Unique Mechanism
Segment: عيادات الأسنان الخاصة التي لديها خدمة جيدة لكن رسائلها الحالية عامة ولا تخلق فرقاً واضحاً للمريض.
Moment: عندما تبدأ العيادة في دفع ميزانية تسويق أو تلاحظ أن الاستفسارات موجودة لكن الحجوزات المؤكدة أقل من المتوقع.
Unique Mechanism: تحويل عرض العيادة إلى رسالة قرار واضحة تربط بين الاعتراضات المتكررة والدليل المناسب ثم اختبارها على نقاط الاحتكاك الأولى حتى تتحول الرسائل إلى حجوزات مؤهلة لا إلى تفاعل شكلي.

## Positioning الداخلي بصيغته الكاملة
Positioning الداخلي: نساعد عيادات الأسنان التي دخلت مرحلة التسويق لكنها ما زالت تخسر حجوزات بسبب الرسائل العامة على بناء تموضع ورسائل بيع تقود إلى حجوزات مؤهلة، من خلال تشخيص اعتراضات المريض أولاً ثم صياغة رسالة قرار واختبارها قبل التوسع.

## لمن لا نخدم ولماذا
- لا نخدم العيادات التي تريد فقط محتوى تجميلياً بدون مراجعة العرض أو الرسالة.
- لا نعمل مع من يبحث عن وعود سريعة بلا التزام بقياس الحجوزات أو اختبار الرسائل.

## لماذا نحن (Value Proposition + زاوية التميز)
Value Proposition: بدل أن نصرف الميزانية على رسائل عامة، نبني للعيادة رسالة واضحة تقلل التردد وتدفع المريض إلى حجز أقرب وأسرع.
زاوية التميز: نبدأ من نقطة القرار عند المريض، لا من كثافة النشر، ثم نحول هذا الفهم إلى أصول يمكن استخدامها في الإعلان والهبوط والمتابعة بنفس المنطق.

## أسباب الثقة (Reasons to believe)
- العمل يبدأ بتشخيص الرسالة الحالية والاعتراضات المتكررة قبل كتابة أي أصل جديد.
- كل وعد في الرسائل يجب أن يرتبط بدليل أو عنصر طمأنة يمكن للعيادة الدفاع عنه.
- القياس النهائي مرتبط بالحجوزات المؤهلة ومعدل الرد، لا بانطباع عام عن الحضور.

## ما لا نكونه (حدود البراند)
- لسنا جهة تبيع ضجيجاً إعلانياً أو نشر محتوى بلا أثر على الحجوزات.
- لا نقدم تصميماً منفصلاً عن الرسالة أو المتابعة أو سبب الثقة.

## الرسائل الجاهزة: Elevator pitch + نسخة قصيرة للموقع + رسالة بيع افتتاحية
Elevator pitch: نساعد عيادات الأسنان التي تحصل على استفسارات أكثر من حجوزات على تحويل الرسائل العامة إلى تموضع واضح يقود إلى حجوزات مؤهلة.
نسخة قصيرة للموقع: رسائل واضحة تساعد عيادتك على تحويل الاهتمام إلى حجوزات مؤكدة بثقة أكبر ومبالغة أقل.
رسالة بيع افتتاحية: إذا كانت عيادتك تجذب اهتماماً لكن الحجوزات أقل مما يجب، فنحن نعيد بناء الرسالة والعرض حول ما يجعل المريض يقرر الحجز فعلاً.

## Framework العمل: كيف نعمل من التشخيص إلى الرسالة إلى الاختبار
1. تشخيص الرسالة الحالية والاعتراضات التي تمنع الحجز.
2. تحديد Segment الدقيق ولحظة القرار التي تسبق الحجز.
3. صياغة التموضع ورسائل البيع الأساسية وربطها بسبب ثقة حقيقي.
4. تحويل الرسائل إلى أصول جاهزة للإعلان والصفحة والمتابعة.
5. اختبار الرسائل الأولى على الحجوزات المؤهلة ثم تعديل الاعتراض أو الخطاف قبل التوسع.

## فرضيات تحتاج تحقق أو بيانات ناقصة
- نحتاج تأكيد أكثر الاعتراضات شيوعاً عند المريض قبل اعتماد الرسالة النهائية على نطاق واسع.
- نحتاج مقارنة بين الرسائل الحالية والجديدة على معدل الحجز المؤكد خلال أول أسبوعين.
TXT;
            }
        };

        $action = new GenerateTemplateDraftAction(
            app(WorkspaceProfileStore::class),
            app(WorkspaceJourneyStore::class),
            $gateway,
            app(StudioOutputQualityGuard::class),
            new WorkspaceGenerationContextBuilder(
                app(WorkspaceProfileStore::class),
                app(WorkspaceJourneyStore::class),
                new StudioAnalyticalDossierBuilder($gateway),
            ),
            app(StudioTemplateContractRegistry::class),
            app(StudioTemplateReadinessGate::class),
        );

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project,
            actor: $user,
            brief: 'اربط الملف بالثقة والنتيجة.',
        );

        $this->assertSame('completed', $generation->status);
        $this->assertNotEmpty($gateway->calls);
        $generationPrompt = $gateway->calls[1]['prompt'] ?? $gateway->calls[0]['prompt'];

        $this->assertStringContainsString('الملف التحليلي المرجعي الإلزامي', $generationPrompt);
        $this->assertStringContainsString('العيادة لا تريد وعوداً فضفاضة.', $generationPrompt);
        $this->assertStringContainsString('راجع الرسائل قبل التسليم النهائي.', $generationPrompt);
        $this->assertStringContainsString('خفف اللغة العامة في بداية الملف.', $generationPrompt);
        $this->assertStringContainsString('عرض قوي يحتاج تمييزاً أوضح', $generationPrompt);
        $this->assertStringContainsString('لهجة خليجية (مبسطة للمحتوى)', $generationPrompt);

        $storedContext = $generation->inputs_json['generation_context'] ?? [];

        $this->assertSame('offer-builder', $storedContext['tool_summaries'][0]['tool_code'] ?? null);
        $this->assertSame('راجع الرسائل قبل التسليم النهائي.', $storedContext['approval_notes'][0]['note'] ?? null);
        $this->assertSame('العيادة لا تريد وعوداً فضفاضة.', $storedContext['client_notes'][0] ?? null);
        $this->assertCount(2, $storedContext['comment_notes'] ?? []);
        $this->assertSame($toolRun->id, $storedContext['tool_runs'][0]['id'] ?? null);
        $this->assertNotEmpty($generation->inputs_json['analysis_dossier']['guide_markdown'] ?? '');
    }

    #[Test]
    public function it_returns_needs_input_instead_of_saving_a_weak_brand_positioning_output_when_context_is_missing(): void
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Thin Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Thin Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'key' => 'business.profile',
            'value_json' => [
                'persona' => 'agency_owner',
                'primary_goal' => 'more_clients',
                'audience' => '',
                'country' => 'السعودية',
                'content_locale' => 'ar_gulf',
            ],
        ]);

        $template = AITemplate::query()->create([
            'code' => 'brand-positioning',
            'name' => 'Brand Positioning',
            'description' => 'Creates a positioning pack.',
            'prompt_template' => 'ابنِ ملف تمركز لمشروع {{project_name}} يستهدف {{audience}}.',
            'model' => 'gpt-5',
            'credit_cost' => 0,
            'status' => 'published',
            'system_role' => 'أنت استراتيجي براند يكتب ملفات تنفيذية.',
            'output_contract_json' => [
                'sections' => ['المنفذون المستهدفون', 'التمركز', 'الرسائل', 'القياس'],
            ],
        ]);

        $gateway = new class implements AiGatewayInterface
        {
            public int $calls = 0;

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                $this->calls++;

                return json_encode([
                    'client_personality_summary' => 'تحليل أولي محدود.',
                    'voice_and_tone' => [
                        'dialect' => 'لهجة خليجية (مبسطة للمحتوى)',
                        'register' => 'مهني مباشر',
                        'style' => 'مباشر',
                        'pace' => 'مختصر',
                        'persuasion_style' => 'النتيجة أولاً',
                    ],
                    'decision_drivers' => [
                        'trust_builders' => ['الوضوح'],
                        'decision_triggers' => ['فائدة مباشرة'],
                        'objections_or_fears' => ['العمومية'],
                        'aversion_triggers' => ['الحشو'],
                    ],
                    'content_preferences' => [
                        'preferred_angles' => ['النتيجة'],
                        'preferred_patterns' => ['جمل مباشرة'],
                        'avoided_patterns' => ['الإنشاء'],
                    ],
                    'brand_dictionary' => [
                        'preferred_keywords' => ['وضوح'],
                        'preferred_phrases' => ['نتيجة واضحة'],
                        'phrases_to_avoid' => ['أفضل خدمة'],
                        'cta_patterns' => ['ابدأ بخطوة واضحة'],
                    ],
                    'execution_rules' => ['تجنب العمومية'],
                    'strategic_summary' => 'ما زالت البيانات ضعيفة.',
                ], JSON_UNESCAPED_UNICODE);
            }
        };

        $action = new GenerateTemplateDraftAction(
            app(WorkspaceProfileStore::class),
            app(WorkspaceJourneyStore::class),
            $gateway,
            app(StudioOutputQualityGuard::class),
            new WorkspaceGenerationContextBuilder(
                app(WorkspaceProfileStore::class),
                app(WorkspaceJourneyStore::class),
                new StudioAnalyticalDossierBuilder($gateway),
            ),
            app(StudioTemplateContractRegistry::class),
            app(StudioTemplateReadinessGate::class),
        );

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: null,
            actor: $user,
            brief: null,
        );

        $this->assertSame('needs_input', $generation->status);
        $this->assertStringContainsString('لم يتم إنتاج ملف نهائي بعد', $generation->output ?? '');
        $this->assertStringContainsString('الأسئلة التي يجب حسمها', $generation->output ?? '');
        $this->assertSame(1, $gateway->calls);
    }

    #[Test]
    public function it_escalates_strategic_quality_failure_to_needs_input_instead_of_saving_a_rejected_positioning_file(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();

        $template = AITemplate::query()->create([
            'code' => 'brand-positioning',
            'name' => 'Brand Positioning',
            'description' => 'Creates a positioning pack.',
            'prompt_template' => 'ابنِ ملف تمركز لمشروع {{project_name}} يستهدف {{audience}}.',
            'model' => 'gpt-5',
            'credit_cost' => 0,
            'status' => 'published',
            'system_role' => 'أنت استراتيجي براند يكتب ملفات تنفيذية.',
            'output_contract_json' => [
                'sections' => ['المنفذون المستهدفون', 'التمركز', 'الرسائل', 'القياس'],
            ],
        ]);

        $gateway = new class implements AiGatewayInterface
        {
            public int $calls = 0;

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                $this->calls++;

                if ($this->calls === 1) {
                    return json_encode([
                        'client_personality_summary' => 'عميل يفضّل النتيجة الواضحة ويكره العمومية.',
                        'voice_and_tone' => [
                            'dialect' => 'لهجة خليجية (مبسطة للمحتوى)',
                            'register' => 'مهني مباشر',
                            'style' => 'مختصر وواضح',
                            'pace' => 'متوسط',
                            'persuasion_style' => 'النتيجة ثم الدليل',
                        ],
                        'decision_drivers' => [
                            'trust_builders' => ['إثبات النتيجة'],
                            'decision_triggers' => ['وضوح الفائدة'],
                            'objections_or_fears' => ['الوعود الفضفاضة'],
                            'aversion_triggers' => ['الكلام العام'],
                        ],
                        'content_preferences' => [
                            'preferred_angles' => ['وضوح الفائدة'],
                            'preferred_patterns' => ['جمل قصيرة'],
                            'avoided_patterns' => ['الحشو'],
                        ],
                        'brand_dictionary' => [
                            'preferred_keywords' => ['حجوزات', 'وضوح'],
                            'preferred_phrases' => ['نتيجة واضحة'],
                            'phrases_to_avoid' => ['حلول عملية'],
                            'cta_patterns' => ['ابدأ بمراجعة واضحة'],
                        ],
                        'execution_rules' => ['ابدأ بالنتيجة'],
                        'strategic_summary' => 'السياق جيد لكن ما زال يحتاج قرار تموضع أدق.',
                    ], JSON_UNESCAPED_UNICODE);
                }

                return <<<'TXT'
## المنفذون المستهدفون
- كاتب محتوى: يراجع الملف.

## Positioning الداخلي بصيغته الكاملة
Positioning الداخلي: نحن نساعد المشاريع الصغيرة على التسويق بشكل أفضل وتقديم حلول تسويق عملية.

## لماذا نحن (Value Proposition + زاوية التميز)
Value Proposition: نقدم حلول تسويق عملية وسريعة.

## أسباب الثقة (Reasons to believe)
- نسبة نجاح عالية
- عملاء يثقون بنا

## الرسائل الجاهزة: Elevator pitch + نسخة قصيرة للموقع + رسالة بيع افتتاحية
Elevator pitch: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.
نسخة قصيرة للموقع: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.
رسالة بيع افتتاحية: نساعد المشاريع الصغيرة على التسويق بشكل أفضل.

## لمن لا نخدم ولماذا
إدارة محتوى شهرية وشراكة نمو

## Framework العمل: كيف نعمل من التشخيص إلى الرسالة إلى الاختبار
تحليل
تنفيذ
تحسين
TXT;
            }
        };

        $action = new GenerateTemplateDraftAction(
            app(WorkspaceProfileStore::class),
            app(WorkspaceJourneyStore::class),
            $gateway,
            app(StudioOutputQualityGuard::class),
            new WorkspaceGenerationContextBuilder(
                app(WorkspaceProfileStore::class),
                app(WorkspaceJourneyStore::class),
                new StudioAnalyticalDossierBuilder($gateway),
            ),
            app(StudioTemplateContractRegistry::class),
            app(StudioTemplateReadinessGate::class),
        );

        $generation = $action->handle(
            workspace: $workspace,
            template: $template,
            project: $project,
            actor: $user,
            brief: 'ابن القرار على مادة حقيقية.',
        );

        $this->assertSame('needs_input', $generation->status);
        $this->assertStringContainsString('لم يتم اعتماد الملف النهائي بعد', $generation->output ?? '');
        $this->assertStringContainsString('الأسئلة التي يجب حسمها قبل إعادة التوليد', $generation->output ?? '');
        $this->assertArrayHasKey('quality_assessment', $generation->inputs_json);
        $this->assertNotEmpty($generation->inputs_json['quality_assessment']['issues'] ?? []);
        $this->assertSame(3, $gateway->calls);
    }

    /**
     * @return array{user: User, workspace: Workspace, project: Project, toolRun: ToolRun}
     */
    private function makeWorkspaceScenario(): array
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Studio Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Studio Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Dental Client',
            'contact_info' => [
                'notes' => 'العيادة لا تريد وعوداً فضفاضة.',
            ],
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Dental Growth',
            'stage' => 3,
            'status' => 'active',
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'key' => 'business.profile',
            'value_json' => [
                'persona' => 'agency_owner',
                'primary_goal' => 'more_clients',
                'audience' => 'مديرو العيادات',
                'country' => 'السعودية',
                'content_locale' => 'ar_gulf',
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'journey.snapshot',
            'value_json' => [
                'current_stage' => 'العرض',
                'current_step' => 'صياغة تمركز البراند',
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'readiness.snapshot',
            'value_json' => [
                ['label' => 'وضوح التمركز', 'score' => 78],
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
            'value_json' => [
                'headline' => 'عرض قوي يحتاج تمييزاً أوضح',
                'text' => 'العرض الحالي جيد لكنه يحتاج اعتراضاً أقوى على الخوف من الهدر.',
                'bullets' => ['اربط العرض بنتيجة محددة', 'خفف أي لغة عامة'],
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.positioning',
            'value_json' => [
                'headline' => 'التموضع يحتاج تضييق الشريحة لا تغيير الوعد فقط',
                'text' => 'الملف السابق كان واسعاً أكثر من اللازم ويحتاج تحديد لحظة الشراء بوضوح.',
                'bullets' => ['حدّد الشريحة الأدق', 'اربط التموضع بلحظة قرار فعلية'],
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.context.offer-builder',
            'value_json' => [
                'project' => ['name' => $project->name, 'stage' => $project->stage],
                'client' => ['name' => $client->name],
                'tool_blueprint' => ['outcome' => 'عرض بيعي جاهز للاختبار'],
            ],
        ]);

        $toolRun = ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => 'offer-builder',
            'mode' => 'guided',
            'inputs_json' => [
                'offer_name' => 'عرض الحجوزات الذكية',
                'offer_result' => 'رفع الحجوزات المؤهلة خلال 30 يوماً',
            ],
            'output_json' => [
                'summary' => 'القوة الأساسية في النتيجة، والضعف في سبب الثقة.',
                'insights' => ['ابدأ بالنتيجة', 'اعرض الاعتراض والرد باختصار'],
            ],
            'summary_json' => [
                'headline' => 'القوة الأساسية في النتيجة والضعف في سبب الثقة',
                'text' => 'الرسالة البيعية تحتاج إثباتاً أقوى قبل التوسع.',
                'bullets' => ['أضف سبب ثقة', 'اكتب اعتراضاً ورداً واضحين'],
            ],
            'next_actions_json' => ['أعد كتابة الخطاف', 'اختبر الرسالة مع العميل'],
            'source_context_json' => [],
            'completeness_score' => 86,
            'created_by' => $user->id,
        ]);

        Approval::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'tool_run',
            'item_id' => $toolRun->id,
            'status' => 'pending',
            'reviewer_id' => $user->id,
            'note' => 'راجع الرسائل قبل التسليم النهائي.',
        ]);

        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'project',
            'entity_id' => $project->id,
            'author_id' => $user->id,
            'body' => 'خفف اللغة العامة في بداية الملف.',
        ]);

        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'tool_run',
            'entity_id' => $toolRun->id,
            'author_id' => $user->id,
            'body' => 'اعتمد على اعتراض الخوف من الهدر.',
        ]);

        return [
            'user' => $user,
            'workspace' => $workspace,
            'project' => $project,
            'toolRun' => $toolRun,
        ];
    }
}
