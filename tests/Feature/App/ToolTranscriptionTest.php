<?php

namespace Tests\Feature\App;

use App\Contracts\AiGatewayInterface;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Speech\SpeechToTextContract;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use App\Support\Workspaces\OnboardingState;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_transcribes_audio_and_maps_it_to_tool_fields(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();

        $this->fakeSpeech('عايز اسوي عرض لأصحاب المطاعم الصغيرة يجيب لهم طلبات اكتر');
        $this->fakeGateway(json_encode([
            'offer_name' => 'نظام محتوى يجلب الطلبات',
            'offer_audience' => 'أصحاب المطاعم الصغيرة',
            'offer_result' => 'طلبات أكثر خلال شهر',
        ], JSON_UNESCAPED_UNICODE));

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.transcribe', $tool), [
                'mode' => 'guided',
                'audio' => File::fake()->create('speech.webm', 12, 'audio/webm'),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ai_mapped', true)
            ->assertJsonPath('fields.offer_audience', 'أصحاب المطاعم الصغيرة')
            ->assertJsonPath('fields.offer_name', 'نظام محتوى يجلب الطلبات');

        $this->assertStringContainsString('المطاعم', (string) $response->json('transcript'));
    }

    #[Test]
    public function it_falls_back_to_raw_transcript_when_ai_mapping_is_unavailable(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();

        $this->fakeSpeech('كلام حر عن المشروع');
        $this->fakeGateway(null); // لا LLM: يجب أن يسقط للنص الخام لا أن يضيع.

        $response = $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.transcribe', $tool), [
                'mode' => 'guided',
                'audio' => File::fake()->create('speech.webm', 12, 'audio/webm'),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ai_mapped', false);

        $this->assertNotEmpty($response->json('fields'));
        $this->assertContains('كلام حر عن المشروع', array_values($response->json('fields')));
    }

    #[Test]
    public function it_returns_422_when_speech_is_unavailable(): void
    {
        [$owner, $workspace, $tool] = $this->scenario();

        $this->app->instance(SpeechToTextContract::class, new class implements SpeechToTextContract
        {
            public function transcribe(string $audioContents, string $filename, ?string $language = null): ?string
            {
                return null;
            }

            public function isAvailable(): bool
            {
                return false;
            }
        });

        $this->actingAs($owner)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->postJson(route('tools.transcribe', $tool), [
                'mode' => 'guided',
                'audio' => File::fake()->create('speech.webm', 12, 'audio/webm'),
            ])
            ->assertStatus(422);
    }

    private function fakeSpeech(?string $transcript): void
    {
        $this->app->instance(SpeechToTextContract::class, new class($transcript) implements SpeechToTextContract
        {
            public function __construct(private readonly ?string $transcript) {}

            public function transcribe(string $audioContents, string $filename, ?string $language = null): ?string
            {
                return $this->transcript;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        });
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
            'name' => 'Voice Account',
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
            'name' => 'Voice Workspace',
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

        Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Voice Project',
            'stage' => 4,
            'status' => 'active',
        ]);

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
