<?php

namespace Tests\Unit;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Services\AIService;
use App\Domain\Account\Models\Account;
use App\Domain\Approval\Models\Approval;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\Project\Models\Project;
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

class AIServiceContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function smart_summary_generation_receives_the_full_aggregated_context(): void
    {
        ['workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();

        $gateway = new class implements AiGatewayInterface
        {
            public string $lastPrompt = '';

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                $this->lastPrompt = $prompt;

                 if (str_contains($prompt, '"client_personality_summary"')) {
                    return json_encode([
                        'client_personality_summary' => 'عميل يريد خطاباً واضحاً بلا إنشائية.',
                        'voice_and_tone' => [
                            'dialect' => 'عربية فصحى معاصرة',
                            'register' => 'مهني مباشر',
                            'style' => 'تحليلي واضح',
                            'pace' => 'مختصر',
                            'persuasion_style' => 'النتيجة ثم الدليل',
                        ],
                        'decision_drivers' => [
                            'trust_builders' => ['الوضوح', 'الضمان'],
                            'decision_triggers' => ['خطوة تالية محددة'],
                            'objections_or_fears' => ['الكلام الإنشائي'],
                            'aversion_triggers' => ['المبالغة'],
                        ],
                        'content_preferences' => [
                            'preferred_angles' => ['ابدأ بالفائدة'],
                            'preferred_patterns' => ['جمل قصيرة'],
                            'avoided_patterns' => ['التعميم'],
                        ],
                        'brand_dictionary' => [
                            'preferred_keywords' => ['وضوح', 'ثقة'],
                            'preferred_phrases' => ['نتيجة يمكن قياسها'],
                            'phrases_to_avoid' => ['أفضل خدمة'],
                            'cta_patterns' => ['ابدأ بمراجعة واضحة'],
                        ],
                        'execution_rules' => ['تجنب الإنشاء'],
                        'strategic_summary' => 'القرار هنا حساس للوضوح أكثر من التجميل.',
                    ], JSON_UNESCAPED_UNICODE);
                }

                return json_encode([
                    'headline' => 'ملخص ذكي',
                    'text' => 'نص تحليلي مختصر.',
                    'bullets' => ['أولوية 1', 'أولوية 2'],
                ], JSON_UNESCAPED_UNICODE);
            }
        };

        $contextBuilder = new WorkspaceGenerationContextBuilder(
            app(WorkspaceProfileStore::class),
            app(WorkspaceJourneyStore::class),
            new StudioAnalyticalDossierBuilder($gateway),
        );

        $service = new AIService($gateway, $contextBuilder);

        $result = $service->generateSmartSummary(
            toolCode: 'offer-builder',
            toolName: 'Offer Builder',
            inputs: [
                'offer_name' => 'عرض النتيجة السريعة',
                'offer_result' => 'زيادة الحجوزات خلال 30 يوماً',
            ],
            sourceContext: [
                'workspace_profile' => [
                    'persona' => 'agency_owner',
                    'primary_goal' => 'more_clients',
                ],
                'project' => [
                    'name' => $project->name,
                    'stage' => $project->stage,
                    'status' => $project->status,
                ],
                'client' => [
                    'name' => $project->client->name,
                ],
            ],
            workspaceId: $workspace->id,
            projectId: $project->id,
        );

        $this->assertNotNull($result);
        $this->assertStringContainsString('## الملف التحليلي المرجعي', $gateway->lastPrompt);
        $this->assertStringContainsString('عرض يحتاج توضيح الضمان', $gateway->lastPrompt);
        $this->assertStringContainsString('لا يحب الكلام الإنشائي.', $gateway->lastPrompt);
        $this->assertStringContainsString('راجع الضمان قبل اعتماد العرض.', $gateway->lastPrompt);
        $this->assertStringContainsString('خفف طول الافتتاحية.', $gateway->lastPrompt);
    }

    /**
     * @return array{workspace: Workspace, project: Project}
     */
    private function makeWorkspaceScenario(): array
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'AI Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'AI Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Beta',
            'contact_info' => [
                'notes' => 'لا يحب الكلام الإنشائي.',
            ],
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project Beta',
            'stage' => 2,
            'status' => 'active',
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
            'value_json' => [
                'headline' => 'عرض يحتاج توضيح الضمان',
                'text' => 'العرض جيد لكنه يحتاج حداً أوضح للمخاطرة.',
                'bullets' => ['أضف ضماناً قصيراً'],
            ],
        ]);

        Approval::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'item_type' => 'tool_run',
            'item_id' => 10,
            'status' => 'pending',
            'reviewer_id' => $user->id,
            'note' => 'راجع الضمان قبل اعتماد العرض.',
        ]);

        Comment::query()->create([
            'workspace_id' => $workspace->id,
            'entity_type' => 'project',
            'entity_id' => $project->id,
            'author_id' => $user->id,
            'body' => 'خفف طول الافتتاحية.',
        ]);

        return [
            'workspace' => $workspace,
            'project' => $project,
        ];
    }
}
