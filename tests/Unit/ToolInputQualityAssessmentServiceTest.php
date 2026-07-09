<?php

namespace Tests\Unit;

use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Services\AiMetrics;
use App\Domain\AI\Services\QualityJudge;
use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\AI\StudioAnalyticalDossierBuilder;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolInputQualityAssessmentService;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolInputQualityAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_flags_generic_inputs_even_when_most_fields_are_filled(): void
    {
        ['workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();
        $service = $this->makeAssessmentService();

        $assessment = $service->assess(
            toolCode: 'ideal-customer',
            toolName: 'Ideal Customer',
            inputs: [
                'customer_type' => 'المشاريع الصغيرة',
                'customer_problem' => 'مشكلة تسويق',
                'customer_goal' => 'تحسين الحضور',
            ],
            mode: 'guided',
            workspaceId: $workspace->id,
            projectId: $project->id,
        );

        $this->assertLessThan(65, $assessment['score']);
        $this->assertTrue(collect($assessment['gaps'])->contains(fn (string $gap): bool => str_contains($gap, 'القيمة')));
        $this->assertStringContainsString('وصف عام', $assessment['field_notes']['customer_type'] ?? '');
        $this->assertLessThan(60, $assessment['dimensions'][1]['score'] ?? 100);
    }

    #[Test]
    public function it_rewards_specific_and_context_aligned_inputs(): void
    {
        ['workspace' => $workspace, 'project' => $project] = $this->makeWorkspaceScenario();
        $service = $this->makeAssessmentService();

        $assessment = $service->assess(
            toolCode: 'ideal-customer',
            toolName: 'Ideal Customer',
            inputs: [
                'customer_type' => 'أصحاب المطاعم المحلية الجديدة في أول سنة تشغيل داخل الرياض',
                'customer_problem' => 'يعانون من تذبذب الطلبات لأن المحتوى الحالي لا يحول المتابعين إلى طلب فعلي ولا يوضح العرض بشكل مطمئن',
                'customer_goal' => 'رفع الطلبات المباشرة خلال 30 يوماً مع تقليل الوقت الذي يقضيه صاحب المطعم في متابعة النشر',
            ],
            mode: 'guided',
            workspaceId: $workspace->id,
            projectId: $project->id,
        );

        $this->assertGreaterThanOrEqual(70, $assessment['score']);
        $this->assertGreaterThanOrEqual(70, $assessment['dimensions'][1]['score'] ?? 0);
        $this->assertGreaterThanOrEqual(65, $assessment['dimensions'][3]['score'] ?? 0);
        $this->assertTrue(collect($assessment['strengths'])->isNotEmpty());
        $this->assertSame('strong', $assessment['field_scores']['customer_problem']['status'] ?? null);
    }

    private function makeAssessmentService(): ToolInputQualityAssessmentService
    {
        $gateway = new class implements AiGatewayInterface
        {
            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                if (str_contains($prompt, '"client_personality_summary"')) {
                    return json_encode([
                        'client_personality_summary' => 'صاحب المشروع يريد لغة واضحة ومباشرة.',
                        'voice_and_tone' => [
                            'dialect' => 'عربية فصحى معاصرة',
                            'register' => 'مهني مباشر',
                            'style' => 'واضح',
                            'pace' => 'مختصر',
                            'persuasion_style' => 'النتيجة ثم الدليل',
                        ],
                        'decision_drivers' => [
                            'trust_builders' => ['الوضوح'],
                            'decision_triggers' => ['نتيجة قابلة للملاحظة'],
                            'objections_or_fears' => ['العمومية'],
                            'aversion_triggers' => ['الحشو'],
                        ],
                        'content_preferences' => [
                            'preferred_angles' => ['النتيجة'],
                            'preferred_patterns' => ['جمل مباشرة'],
                            'avoided_patterns' => ['التعميم'],
                        ],
                        'brand_dictionary' => [
                            'preferred_keywords' => ['طلبات', 'وضوح'],
                            'preferred_phrases' => ['نتيجة واضحة'],
                            'phrases_to_avoid' => ['حلول متكاملة'],
                            'cta_patterns' => ['ابدأ بخطوة واضحة'],
                        ],
                        'execution_rules' => ['تجنب العمومية'],
                        'strategic_summary' => 'الجمهور يجب أن يكون عملياً وواضحاً.',
                    ], JSON_UNESCAPED_UNICODE);
                }

                return null;
            }
        };

        return new ToolInputQualityAssessmentService(
            new ToolBlueprintCatalog,
            new WorkspaceGenerationContextBuilder(
                app(WorkspaceProfileStore::class),
                app(WorkspaceJourneyStore::class),
                new StudioAnalyticalDossierBuilder($gateway),
            ),
            new QualityJudge($gateway, app(AiMetrics::class)),
        );
    }

    /**
     * @return array{workspace: Workspace, project: Project}
     */
    private function makeWorkspaceScenario(): array
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Assessment Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Assessment Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Client Gamma',
            'contact_info' => [
                'notes' => 'يريد طلبات أسرع ورسائل أوضح.',
            ],
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Project Gamma',
            'stage' => 2,
            'status' => 'active',
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'key' => 'business.profile',
            'value_json' => [
                'audience' => 'أصحاب المطاعم المحلية الجديدة',
                'primary_goal' => 'more_clients',
                'country' => 'السعودية',
                'content_locale' => 'ar_gulf',
            ],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'key' => 'tool.summary.offer-builder',
            'value_json' => [
                'headline' => 'العرض يحتاج ربطاً أوضح بالطلبات',
                'text' => 'العميل يريد زيادة الطلبات لا مجرد محتوى.',
                'bullets' => ['اربط الرسالة بالطلبات المباشرة'],
            ],
        ]);

        return [
            'workspace' => $workspace,
            'project' => $project,
        ];
    }
}
