<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Intake\Contracts\SpeechToText;
use App\Modules\Measurement\QueryBudgetManager;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * الاستقبال الصوتي: يرفع اكتمال المحاور ١–٦ بلا كتابة.
 *
 * الحدّان المحروسان هنا: النسخ لا يكتب حقيقة (يعيد نصًّا للمراجعة)، والتكلفة
 * تمرّ من سقف المساحة كأي قدرة متغيرة.
 */
class VoiceIntakeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_recording_comes_back_as_text_for_review_not_as_a_fact(): void
    {
        [$user, $project] = $this->owned();
        $this->speechReturning('نبيع قهوة مختصة للمقاهي الصغيرة في الرياض.');

        $response = $this->actingAs($user)
            ->post(route('app.voice.store', $project), [
                'audio' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg'),
                'seconds' => 30,
            ])
            ->assertOk();

        $response->assertJsonPath('data.text', 'نبيع قهوة مختصة للمقاهي الصغيرة في الرياض.');

        // الوسم يسافر مع النص، والدماغ لم يُكتب فيه شيء.
        $response->assertJsonPath('data.needs_review', true);
        $this->assertSame(0, $project->brainFacts()->count());
    }

    #[Test]
    public function the_transcription_is_charged_against_the_workspace_cap(): void
    {
        [$user, $project] = $this->owned();
        $this->speechReturning('نص', durationSeconds: 90.0, costUsd: 0.004);

        $this->actingAs($user)->post(route('app.voice.store', $project), [
            'audio' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg'),
            'seconds' => 120,
        ])->assertOk();

        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();

        // حُجزت مواضع دقيقتين وسُوّيت على دقيقة ونصف فعلية → موضعان.
        $this->assertSame(2, $budget->consumed);
        $this->assertSame(0, $budget->reserved);
        $this->assertEqualsWithDelta(0.004, $budget->cost_usd, 0.000001);
    }

    #[Test]
    public function an_exhausted_cap_refuses_before_the_provider_is_called(): void
    {
        [$user, $project] = $this->owned();
        $project->workspace->forceFill(['monthly_query_limit' => 0])->save();

        $called = false;
        $this->speechReturning('نص', onCall: function () use (&$called): void {
            $called = true;
        });

        $this->actingAs($user)->post(route('app.voice.store', $project), [
            'audio' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg'),
            'seconds' => 30,
        ])->assertStatus(422);

        $this->assertFalse($called, 'استُدعي المزوّد رغم نفاد السقف.');
    }

    #[Test]
    public function a_provider_failure_returns_the_places_and_says_so_plainly(): void
    {
        [$user, $project] = $this->owned();

        $this->app->bind(SpeechToText::class, fn () => new class implements SpeechToText
        {
            public function transcribe(string $path, string $locale = 'ar'): array
            {
                throw new RuntimeException('المزوّد لا يستجيب.');
            }

            public function name(): string
            {
                return 'fake';
            }
        });

        $this->actingAs($user)->post(route('app.voice.store', $project), [
            'audio' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg'),
            'seconds' => 30,
        ])->assertStatus(422)->assertJsonPath('message', 'تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.');

        $budget = app(QueryBudgetManager::class)->budgetFor($project->workspace)->fresh();

        // لم يحصل على نصّ فلا يُحاسَب.
        $this->assertSame(0, $budget->reserved);
        $this->assertSame(0, $budget->consumed);
    }

    #[Test]
    public function another_workspace_cannot_transcribe_into_someone_elses_business(): void
    {
        [, $project] = $this->owned();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('app.voice.store', $project), [
            'audio' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg'),
            'seconds' => 30,
        ])->assertNotFound();
    }

    private function speechReturning(
        string $text,
        float $durationSeconds = 20.0,
        float $costUsd = 0.001,
        ?\Closure $onCall = null,
    ): void {
        $this->app->bind(SpeechToText::class, fn () => new class($text, $durationSeconds, $costUsd, $onCall) implements SpeechToText
        {
            public function __construct(
                private readonly string $text,
                private readonly float $duration,
                private readonly float $cost,
                private readonly ?\Closure $onCall,
            ) {}

            public function transcribe(string $path, string $locale = 'ar'): array
            {
                ($this->onCall ?? static fn () => null)();

                return [
                    'text' => $this->text,
                    'duration_seconds' => $this->duration,
                    'cost_usd' => $this->cost,
                ];
            }

            public function name(): string
            {
                return 'fake';
            }
        });
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function owned(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);
        $project->brainFacts()->delete();
        $project->workspace->forceFill(['monthly_query_limit' => 50])->save();

        return [$user, $project->fresh()];
    }
}
