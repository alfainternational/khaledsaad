<?php

namespace Tests\Feature;

use App\Models\MessageTestBatch;
use App\Models\MessageTestResult;
use App\Models\MessageVariant;
use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\User;
use App\Services\Messaging\MessageSchemas;
use App\Services\Messaging\MessageSuggestionService;
use App\Services\Messaging\MessageTestService;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Services\Projects\ProjectService;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * استوديو الرسائل: الشخصية وحدة العمل من العقد إلى قاعدة البيانات.
 *
 * ما يُحرَس هنا ليس شكل الواجهة بل ثلاث قواعد لا تُرى إن انكسرت بصمت:
 * ألّا تُنتَج رسالة موحّدة، وألّا يُكتب فوق نصٍّ اختُبرت عليه درجة،
 * وألّا تُختلق نتيجة لشخصية لم يردّ عنها النموذج.
 */
class MessageStudioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_suggestion_contract_forbids_a_shared_message(): void
    {
        $keys = ['key-a', 'key-b', 'key-c'];
        $schema = MessageSchemas::suggestions($keys, 180);
        $items = $schema['properties']['messages'];

        // عنصر لكل مفتاح بالضبط: لا نصٌّ واحد يمثّل الجميع، ولا شخصية تُسقَط.
        $this->assertSame(3, $items['minItems']);
        $this->assertSame(3, $items['maxItems']);
        $this->assertSame($keys, $items['items']['properties']['persona_key']['enum']);
        $this->assertSame(180, $items['items']['properties']['content']['maxLength']);
        $this->assertArrayNotHasKey('overall_message', $schema['properties']);

        $tests = MessageSchemas::tests($keys);
        $this->assertSame(['comparison', 'next_experiment'], $tests['properties']['summary']['required']);
        $this->assertArrayNotHasKey('improved_version', $tests['properties']['summary']['properties']);
    }

    #[Test]
    public function the_persona_key_survives_reordering_the_panel(): void
    {
        $service = app(PersonaMessageProfileService::class);
        $persona = ['name' => 'سارة', 'role' => 'صاحبة متجر'];

        // المفتاح من الهوية لا من الفهرس: إعادة البناء تعيد الترتيب لا الهوية.
        $this->assertSame($service->keyFor($persona), $service->keyFor($persona + ['age_range' => '30-40']));
        $this->assertNotSame($service->keyFor($persona), $service->keyFor(['name' => 'ماجد', 'role' => 'صاحبة متجر']));
    }

    #[Test]
    public function suggesting_writes_one_independent_draft_per_persona(): void
    {
        [$user, $panel] = $this->panel();
        $keys = array_keys(app(PersonaMessageProfileService::class)->profiles($panel));
        $this->fakeRunner(fn (AIRequest $request) => [
            'messages' => array_map(fn (string $key, int $index) => [
                'persona_key' => $key,
                'content' => "نصٌّ خاص بالشخصية رقم {$index} لا يصلح لغيرها إطلاقًا.",
                'teaching_note' => 'يخاطب اعتراضها وحدها ولا يجمع اعتراضات غيرها.',
                'reusable_formula' => '[الوجع] + [الدليل] + [الإجراء]',
            ], $keys, array_keys($keys)),
        ]);

        $outcome = app(MessageSuggestionService::class)->suggest(
            $panel, $keys, MessageChannel::Ad, MessageObjective::Attention, $user,
        );

        $this->assertCount(count($keys), $outcome['variants']);
        $this->assertSame([], $outcome['failed']);
        $this->assertCount(
            count($keys),
            MessageVariant::pluck('content')->unique(),
            'كل شخصية تحتاج نصًّا مستقلًّا لا نسخة مشتركة.',
        );
        $this->assertSame(
            [MessageVariant::ORIGIN_SUGGESTED],
            MessageVariant::pluck('origin')->unique()->all(),
        );
    }

    #[Test]
    public function a_failed_persona_never_erases_the_drafts_that_succeeded(): void
    {
        [$user, $panel] = $this->panel();
        $keys = array_keys(app(PersonaMessageProfileService::class)->profiles($panel));

        // النموذج يردّ عن الأولى فقط في كل محاولة.
        $this->fakeRunner(fn () => [
            'messages' => [[
                'persona_key' => $keys[0],
                'content' => 'نصٌّ مكتمل للشخصية الأولى وحدها دون غيرها من الشخصيات.',
                'teaching_note' => 'يعالج اعتراضها المحدد.',
                'reusable_formula' => '[الوجع] + [الإجراء]',
            ]],
        ]);

        $outcome = app(MessageSuggestionService::class)->suggest(
            $panel, $keys, MessageChannel::Ad, MessageObjective::Attention, $user,
        );

        $this->assertCount(1, $outcome['variants']);
        $this->assertSame(array_slice($keys, 1), $outcome['failed']);
        $this->assertSame(1, MessageVariant::count());
    }

    #[Test]
    public function testing_scores_each_persona_against_its_own_message_only(): void
    {
        [$user, $panel] = $this->panel();
        $variants = $this->draftsFor($panel, $user);
        $sent = null;

        $this->fakeRunner(function (AIRequest $request) use ($variants, &$sent) {
            $sent = $request;

            return [
                'results' => $variants->map(fn (MessageVariant $variant, int $index) => [
                    'persona_key' => $variant->persona_key,
                    'score' => 60 + $index,
                    'reaction' => 'قرأتها وشعرت أنها تخصّني أنا تحديدًا لا غيري.',
                    'strength' => 'الجملة الأولى تمسّ وجعها.',
                    'objection' => 'ما زالت تحتاج رقمًا.',
                    'revised_content' => "تعديل يخص {$variant->persona_key} وحدها دون سواها.",
                ])->all(),
                'summary' => [
                    'comparison' => 'الفروق بين الشخصيات واضحة في موضع الدليل.',
                    'next_experiment' => 'جرّب رقمًا صريحًا مع الحسّاس للسعر.',
                ],
            ];
        });

        $batch = app(MessageTestService::class)->test($panel, $variants, $user, MessageTestBatch::MODE_BATCH);

        $this->assertSame(MessageTestBatch::STATUS_COMPLETE, $batch->status);
        $this->assertArrayNotHasKey('improved_version', $batch->summary);

        foreach ($batch->results as $result) {
            // النتيجة مربوطة بالإصدار الذي قيست عليه، لا بالشخصية وحدها.
            $this->assertSame($result->persona_key, $result->variant->persona_key);
            $this->assertSame(MessageVariant::STATUS_TESTED, $result->variant->status);
        }

        $payload = json_decode(json_encode($sent->messages), true);
        $this->assertStringContainsString('ولا تقيّم شخصية برسالة غيرها', $payload[0]['content']);
    }

    #[Test]
    public function a_partial_batch_names_the_missing_personas_and_invents_nothing(): void
    {
        [$user, $panel] = $this->panel();
        $variants = $this->draftsFor($panel, $user);
        $answered = $variants->first();

        $this->fakeRunner(fn () => [
            'results' => [[
                'persona_key' => $answered->persona_key,
                'score' => 71,
                'reaction' => 'الرسالة تخاطبني بلغتي وتعرف ما يقلقني فعلًا.',
                'strength' => 'الوضوح.',
                'objection' => 'أحتاج ضمانًا.',
                'revised_content' => 'تعديل خاص بهذه الشخصية وحدها بلا دمج.',
            ]],
            'summary' => ['comparison' => 'لم تكتمل المقارنة لغياب البقية.', 'next_experiment' => 'أعد اختبار الناقص.'],
        ]);

        $batch = app(MessageTestService::class)->test($panel, $variants, $user, MessageTestBatch::MODE_BATCH);

        $this->assertSame(MessageTestBatch::STATUS_PARTIAL, $batch->status);
        $this->assertSame(1, $batch->results()->count());
        $this->assertCount($variants->count() - 1, $batch->summary['incomplete']);
        // الشخصيات الناقصة تبقى مسودات — لا نتيجة مختلقة ولا حالة مزوّرة.
        $this->assertSame(
            $variants->count() - 1,
            MessageVariant::where('status', MessageVariant::STATUS_DRAFT)->count(),
        );
    }

    #[Test]
    public function revising_creates_a_new_version_and_keeps_the_tested_one(): void
    {
        [$user, $panel] = $this->panel();
        $variant = $this->draftsFor($panel, $user)->first();
        $variant->update(['status' => MessageVariant::STATUS_TESTED]);

        $batch = MessageTestBatch::create([
            'project_id' => $panel->project_id, 'persona_panel_id' => $panel->id,
            'user_id' => $user->id, 'mode' => 'single', 'status' => 'complete',
        ]);
        $result = MessageTestResult::create([
            'message_test_batch_id' => $batch->id,
            'message_variant_id' => $variant->id,
            'persona_key' => $variant->persona_key,
            'score' => 64,
            'reaction' => 'قريبة لكنها تحتاج دليلًا صريحًا قبل أن أتحرك.',
            'revised_content' => 'نصٌّ معدَّل يضيف الدليل الذي طلبته هذه الشخصية.',
        ]);

        $revision = app(MessageTestService::class)->reviseFrom($result, $user);

        $this->assertSame($variant->id, $revision->parent_id);
        $this->assertSame(MessageVariant::ORIGIN_REVISED, $revision->origin);
        $this->assertSame(MessageVariant::STATUS_DRAFT, $revision->status);
        // الأصل المختبَر لم يُمسّ: درجته تبقى مفهومة لأن نصّها لم يتغيّر.
        $this->assertSame(MessageTestResult::first()->message_variant_id, $variant->fresh()->id);
        $this->assertNotSame($variant->fresh()->content, $revision->content);
    }

    #[Test]
    public function a_message_from_another_project_is_refused(): void
    {
        [$user, $panel] = $this->panel();
        [, $otherPanel] = $this->panel('مشروع آخر');
        $foreign = $this->draftsFor($otherPanel, $user)->first();

        $this->expectExceptionMessage('رسالة لا تخص لوحة هذا المشروع.');

        app(MessageTestService::class)->test($panel, collect([$foreign]), $user, 'single');
    }

    #[Test]
    public function the_studio_screen_shows_a_tab_and_an_editor_for_every_persona(): void
    {
        [$user, $panel] = $this->panel();
        $this->draftsFor($panel, $user);

        $response = $this->actingAs($user)
            ->get(route('app.messages.studio', $panel->project))
            ->assertOk();

        foreach ($panel->personas as $persona) {
            $response->assertSee($persona['name'], false);
            $response->assertSee($persona['locations'][0], false);
        }

        $response->assertSee('اقترح رسائل للجميع', false);
        $response->assertSee('اختبر جميع الرسائل', false);
    }

    /**
     * @return array{0: User, 1: PersonaPanel}
     */
    private function panel(string $name = 'مشروع الرسائل'): array
    {
        $user = User::first() ?? User::factory()->create();

        $project = app(ProjectService::class)->create($user, [
            'name' => $name,
            'industry' => 'التجارة الإلكترونية',
        ]);

        $panel = PersonaPanel::create([
            'project_id' => $project->id,
            'personas' => [
                [
                    'name' => 'سارة المترددة', 'age_range' => '30-40', 'gender' => 'أنثى',
                    'role' => 'صاحبة متجر منزلي', 'locations' => ['الرياض', 'الخرج'],
                    'interests' => ['التجارة الإلكترونية', 'المنتجات اليدوية'],
                    'platforms' => ['إنستغرام'], 'spending_level' => 'متوسط',
                    'pains' => ['خيبات سابقة'], 'motivation' => 'تريد دليلًا قبل أن تدفع.',
                    'objection' => 'ما الذي يثبت أن هذا مختلف؟', 'buying_style' => 'تقارن ثم تقرر',
                    'tone' => 'هادئة موثّقة', 'quote' => 'أرني نتيجة حقيقية.',
                ],
                [
                    'name' => 'ماجد الحسّاس للسعر', 'age_range' => '25-35', 'gender' => 'ذكر',
                    'role' => 'صاحب كافيه', 'locations' => ['جدة'],
                    'interests' => ['العروض', 'التوفير'],
                    'platforms' => ['سناب شات'], 'spending_level' => 'منخفض',
                    'pains' => ['ميزانية ضيقة'], 'motivation' => 'يريد أعلى قيمة بأقل مبلغ.',
                    'objection' => 'أجد أرخص منه.', 'buying_style' => 'يوازن بدقة',
                    'tone' => 'صريحة بالأرقام', 'quote' => 'كم سيكلفني؟',
                ],
            ],
            'source' => 'rules',
            'generated_at' => now(),
        ]);

        return [$user, $panel];
    }

    /**
     * @return \Illuminate\Support\Collection<int, MessageVariant>
     */
    private function draftsFor(PersonaPanel $panel, User $user)
    {
        $profiles = app(PersonaMessageProfileService::class)->profiles($panel);

        return collect(array_keys($profiles))->map(fn (string $key, int $index) => MessageVariant::create([
            'project_id' => $panel->project_id,
            'persona_panel_id' => $panel->id,
            'user_id' => $user->id,
            'persona_key' => $key,
            'channel' => MessageChannel::Ad->value,
            'objective' => MessageObjective::Attention->value,
            'content' => "مسودة رقم {$index} مكتوبة لهذه الشخصية وحدها دون غيرها.",
            'origin' => MessageVariant::ORIGIN_MANUAL,
            'status' => MessageVariant::STATUS_DRAFT,
        ]))->values();
    }

    private function fakeRunner(callable $handler): void
    {
        $this->app->instance(StructuredRunner::class, new class($handler) extends StructuredRunner
        {
            public function __construct(private $handler) {}

            public function run(AIRequest $request, $toolRun = null): array
            {
                return ($this->handler)($request);
            }
        });
    }
}
