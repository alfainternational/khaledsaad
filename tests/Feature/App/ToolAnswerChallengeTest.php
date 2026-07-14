<?php

namespace Tests\Feature\App;

use App\Contracts\AiGatewayInterface;
use App\Domain\Account\Models\Account;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolAnswerChallengeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_follow_up_question_for_a_vague_answer(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();
        $this->fakeGateway('كم عدد المطاعم التي تستهدفها تحديداً وفي أي مدينة؟');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.challenge', $tool), [
                'field' => 'offer_audience',
                'value' => 'ناس كثير',
                'mode' => 'guided',
            ])
            ->assertOk()
            ->assertJsonPath('question', 'كم عدد المطاعم التي تستهدفها تحديداً وفي أي مدينة؟');
    }

    #[Test]
    public function it_returns_null_when_the_answer_is_specific_enough(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();
        $this->fakeGateway('OK');

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.challenge', $tool), [
                'field' => 'offer_audience',
                'value' => 'أصحاب 20 مطعماً صغيراً في الخرطوم يبيعون وجبات سريعة',
                'mode' => 'guided',
            ])
            ->assertOk()
            ->assertJsonPath('question', null);
    }

    #[Test]
    public function it_degrades_to_null_without_ai(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();
        $this->fakeGateway(null);

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.challenge', $tool), [
                'field' => 'offer_audience',
                'value' => 'ناس كثير',
                'mode' => 'guided',
            ])
            ->assertOk()
            ->assertJsonPath('question', null);
    }

    private function fakeGateway(?string $text): void
    {
        $this->app->instance(AiGatewayInterface::class, new class($text) implements AiGatewayInterface
        {
            public function __construct(private readonly ?string $text) {}

            public function requestContent(string $prompt, ?string $systemPrompt = null): ?array
            {
                return null;
            }

            public function generateText(string $prompt, ?string $systemPrompt = null): ?string
            {
                return $this->text;
            }
        });
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Tool}
     */
    private function scenario(): array
    {
        $this->seed(PlatformBootstrapSeeder::class);

        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'agency')->firstOrFail();

        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'Challenge Account',
            'billing_email' => $owner->email,
            'status' => 'active',
        ]);

        Subscription::query()->create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Challenge Workspace',
            'type' => 'team',
            'status' => 'active',
        ]);

        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
        ]);

        app(OnboardingState::class)->markCompleted($workspace);

        $tool = Tool::query()->updateOrCreate(
            ['code' => 'offer-builder'],
            [
                'name' => 'Offer Builder',
                'description' => 'Builds structured offers.',
                'stage' => 4,
                'sort_order' => 1,
                'status' => 'published',
                'has_guided_mode' => true,
                'has_structured_mode' => true,
                'has_expert_mode' => true,
            ],
        );

        return [$owner, $workspace, $tool];
    }
}
