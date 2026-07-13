<?php

namespace Tests\Unit;

use App\Contracts\AiGatewayInterface;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeRetriever;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Approval\Models\Approval;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\AI\StudioAnalyticalDossierBuilder;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceGenerationContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_collects_tool_outputs_notes_and_comments_into_one_generation_context(): void
    {
        ['workspace' => $workspace, 'project' => $project, 'toolRun' => $toolRun] = $this->makeWorkspaceScenario();

        $context = $this->makeContextBuilder()->build($workspace, $project);

        $this->assertCount(1, $context['tool_summaries']);
        $this->assertCount(1, $context['tool_runs']);
        $this->assertCount(1, $context['approval_notes']);
        $this->assertNotEmpty($context['client_notes']);
        $this->assertCount(2, $context['comment_notes']);
        $this->assertNotEmpty($context['analytical_dossier']['guide_markdown'] ?? '');

        $this->assertSame('offer-builder', $context['tool_summaries'][0]['tool_code']);
        $this->assertSame($toolRun->id, $context['tool_runs'][0]['id']);

        $promptBlock = $context['prompt_block'];

        $this->assertStringContainsString('الملف التحليلي المرجعي الإلزامي', $promptBlock);
        $this->assertStringContainsString('لهجة خليجية (مبسطة للمحتوى)', $promptBlock);
        $this->assertStringContainsString('عرض قوي يحتاج اعتراضات أدق', $promptBlock);
        $this->assertStringContainsString('لا يحب الوعود المبالغ فيها.', $promptBlock);
        $this->assertStringContainsString('راجع الاعتراضات قبل اعتماد النص النهائي.', $promptBlock);
        $this->assertStringContainsString('عدّل الزاوية البيعية لتكون أوضح.', $promptBlock);
    }

    #[Test]
    public function it_injects_scoped_knowledge_evidence_with_citation_rules_when_enabled(): void
    {
        config()->set('services.knowledge.retrieval', true);
        ['workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();
        $content = 'الدليل يعتمد مؤشر الفيروز وقيمته 91 وفق الملف المرفق.';
        app(StructuredKnowledgeRepository::class)->storeDocument(
            KnowledgeScope::forProject((int) $workspace->account_id, $workspace->id, $project->id),
            'uploaded_file',
            'upload://context-proof',
            'ملف دليل الفيروز',
            $content,
            [['heading' => 'القياس', 'content' => $content, 'locator' => ['line_start' => 4, 'line_end' => 4]]],
            85,
        );
        $this->assertCount(1, app(KnowledgeRetriever::class)->retrieve(
            KnowledgeScope::forProject((int) $workspace->account_id, $workspace->id, $project->id),
            'مؤشر الفيروز',
        ));

        $context = $this->makeContextBuilder()->build($workspace, $project, 'ما قيمة مؤشر الفيروز؟');

        $this->assertCount(1, $context['knowledge_evidence']);
        $this->assertStringContainsString('ملف دليل الفيروز', $context['prompt_block']);
        $this->assertStringContainsString('[KB:', $context['prompt_block']);
        $this->assertStringContainsString('ميّز بوضوح بين الدليل والاستنتاج', $context['prompt_block']);
    }

    #[Test]
    public function retrieval_flag_off_preserves_the_prompt_without_an_evidence_block(): void
    {
        config()->set('services.knowledge.retrieval', false);
        ['workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();

        $context = $this->makeContextBuilder()->build($workspace, $project);

        $this->assertSame([], $context['knowledge_evidence']);
        $this->assertStringNotContainsString('=== أدلة قاعدة المعرفة ===', $context['prompt_block']);
    }

    private function makeContextBuilder(): WorkspaceGenerationContextBuilder
    {
        $gateway = new class implements AiGatewayInterface
        {
            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                return json_encode([
                    'client_personality_summary' => 'عميل محافظ يريد لغة خليجية مهنية مباشرة ويبتعد عن التهويل.',
                    'voice_and_tone' => [
                        'dialect' => 'لهجة خليجية (مبسطة للمحتوى)',
                        'register' => 'مهني واضح',
                        'style' => 'مباشر مع احترام الحساسية التجارية',
                        'pace' => 'مختصر محسوب',
                        'persuasion_style' => 'الإقناع عبر النتيجة والثقة',
                    ],
                    'decision_drivers' => [
                        'trust_builders' => ['إثبات النتيجة', 'وضوح الرسالة'],
                        'decision_triggers' => ['خطوة عملية واضحة'],
                        'objections_or_fears' => ['الوعود المبالغ فيها'],
                        'aversion_triggers' => ['الحشو'],
                    ],
                    'content_preferences' => [
                        'preferred_angles' => ['البدء بالنتيجة'],
                        'preferred_patterns' => ['جمل مباشرة قصيرة'],
                        'avoided_patterns' => ['المبالغة'],
                    ],
                    'brand_dictionary' => [
                        'preferred_keywords' => ['وضوح', 'نتيجة', 'ثقة'],
                        'preferred_phrases' => ['رسالة واضحة تقود لقرار'],
                        'phrases_to_avoid' => ['أفضل خدمة على الإطلاق'],
                        'cta_patterns' => ['ابدأ بخطوة واضحة'],
                    ],
                    'execution_rules' => ['تجنب الحشو', 'اربط أي وعد بدليل'],
                    'strategic_summary' => 'أي نص لاحق يجب أن يكون منضبطاً وقابلاً للتطبيق.',
                ], JSON_UNESCAPED_UNICODE);
            }
        };

        return new WorkspaceGenerationContextBuilder(
            app(WorkspaceProfileStore::class),
            app(WorkspaceJourneyStore::class),
            new StudioAnalyticalDossierBuilder($gateway),
        );
    }

    /**
     * @return array{workspace: Workspace, project: Project, toolRun: ToolRun}
     */
    private function makeWorkspaceScenario(): array
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Context Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Context Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Alpha',
            'contact_info' => [
                'notes' => "لا يحب الوعود المبالغ فيها.\nيريد لغة مباشرة ومهنية.",
            ],
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project Alpha',
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
                'audience' => 'أصحاب العيادات',
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
                'current_step' => 'صياغة الرسائل',
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'readiness.snapshot',
            'value_json' => [
                ['label' => 'وضوح العرض', 'score' => 75],
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
            'value_json' => [
                'headline' => 'عرض قوي يحتاج اعتراضات أدق',
                'text' => 'العرض واضح لكنه يحتاج براهين أكثر في الرد على التردد.',
                'bullets' => ['أضف ضماناً بسيطاً', 'اربط العرض بنتيجة محددة'],
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.context.offer-builder',
            'value_json' => [
                'project' => ['name' => $project->name, 'stage' => $project->stage],
                'client' => ['name' => $client->name],
                'tool_blueprint' => ['outcome' => 'بناء عرض قابل للبيع فوراً'],
            ],
        ]);

        $toolRun = ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => 'offer-builder',
            'mode' => 'guided',
            'inputs_json' => [
                'offer_name' => 'عرض الاستقطاب',
                'offer_result' => 'زيادة الحجوزات خلال 30 يوماً',
            ],
            'output_json' => [
                'summary' => 'العرض الحالي جيد لكنه يحتاج سبب ثقة أوضح.',
                'insights' => ['ركّز على النتيجة قبل التفاصيل', 'خفف طول الرسالة الأولى'],
            ],
            'summary_json' => [
                'headline' => 'العرض الحالي جيد لكنه يحتاج سبب ثقة أوضح',
                'text' => 'المشكلة ليست في الفكرة بل في قوة التبرير.',
                'bullets' => ['أضف نتيجة ملموسة', 'قدّم اعتراضاً ورداً جاهزاً'],
            ],
            'next_actions_json' => ['أعد كتابة الخطاف الأول', 'اختبر الاعتراض الرئيسي مع العميل'],
            'source_context_json' => [],
            'completeness_score' => 88,
            'created_by' => $user->id,
        ]);

        Approval::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'tool_run',
            'item_id' => $toolRun->id,
            'status' => 'pending',
            'reviewer_id' => $user->id,
            'note' => 'راجع الاعتراضات قبل اعتماد النص النهائي.',
        ]);

        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'project',
            'entity_id' => $project->id,
            'author_id' => $user->id,
            'body' => 'عدّل الزاوية البيعية لتكون أوضح.',
        ]);

        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'tool_run',
            'entity_id' => $toolRun->id,
            'author_id' => $user->id,
            'body' => 'النبرة الحالية جيدة لكن تحتاج إثباتاً أقوى.',
        ]);

        return [
            'workspace' => $workspace,
            'project' => $project,
            'toolRun' => $toolRun,
        ];
    }
}
