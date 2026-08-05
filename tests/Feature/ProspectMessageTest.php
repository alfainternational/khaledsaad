<?php

namespace Tests\Feature;

use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Models\User;
use App\Services\Messaging\MessageSchemas;
use App\Services\Messaging\PersonaMessageProfileService;
use App\Services\Messaging\ProspectMessageService;
use App\Services\Projects\ProjectService;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * رسالة باسم كل عميل متوقع.
 *
 * ثلاث قواعد تُحرَس هنا لأن انكسارها لا يُرى: ألّا يخرج نصٌّ واحد باسم
 * «تخصيص»، ألّا يُخترع عن شخص حقيقي ما لم يُكتب عنه، وألّا يتجاوز التوليد
 * سقف الدفعة صامتًا.
 */
class ProspectMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_contract_binds_one_message_to_each_named_prospect(): void
    {
        $schema = MessageSchemas::prospectMessages(['p1', 'p2'], 320);
        $items = $schema['properties']['messages'];

        $this->assertSame(2, $items['minItems']);
        $this->assertSame(2, $items['maxItems']);
        $this->assertSame(['p1', 'p2'], $items['items']['properties']['prospect_key']['enum']);

        // بلا score ولا reaction: رقمٌ بجانب اسم إنسان يُقرأ كأنه استُطلع.
        $this->assertArrayNotHasKey('score', $items['items']['properties']);
        $this->assertArrayNotHasKey('reaction', $items['items']['properties']);
    }

    #[Test]
    public function every_prospect_gets_a_distinct_message_carrying_what_we_know(): void
    {
        [$user, $project] = $this->project();
        $prospects = $this->prospects($project, $user);
        $sent = null;

        $this->fakeRunner(function (AIRequest $request) use ($prospects, &$sent) {
            $sent = json_encode($request->messages, JSON_UNESCAPED_UNICODE);

            return ['messages' => $prospects->map(fn (Prospect $prospect) => [
                'prospect_key' => 'p'.$prospect->id,
                'content' => "رسالة تخص {$prospect->name} وحده ولا تصلح لغيره إطلاقًا.",
                'why' => 'تبدأ مما دار بينكما لا من عرض عام.',
            ])->all()];
        });

        $outcome = app(ProspectMessageService::class)->generate(
            $project, $prospects, MessageChannel::Whatsapp, MessageObjective::Trust, $user,
        );

        $this->assertCount(2, $outcome['messages']);
        $this->assertSame([], $outcome['failed']);
        $this->assertSame(0, $outcome['skipped']);
        $this->assertCount(2, ProspectMessage::pluck('content')->unique());

        // ما يعرفه صاحب المشروع يصل النموذج — وهو الفرق عن رسالة جماعية مُقنَّعة.
        $this->assertStringContainsString('قابلته في معرض الرياض', $sent);
        $this->assertStringContainsString('نبرة', $sent);

        // التعليمات تمنع الاختلاق صراحةً.
        $this->assertStringContainsString('ممنوع اختلاق أي معلومة عنه', $sent);
    }

    #[Test]
    public function the_batch_ceiling_is_applied_before_the_call_and_announced(): void
    {
        [$user, $project] = $this->project();

        $many = collect(range(1, ProspectMessageService::BATCH_LIMIT + 3))
            ->map(fn (int $index) => Prospect::create([
                'project_id' => $project->id, 'user_id' => $user->id,
                'name' => 'عميل رقم '.$index, 'temperature' => 'warm',
                'preferred_channel' => 'whatsapp', 'status' => Prospect::STATUS_ACTIVE,
            ]));

        $requested = 0;
        $this->fakeRunner(function (AIRequest $request) use (&$requested) {
            // رسالة المستخدم وحدها: التعليمات تذكر المفتاح مرة فتشوّش العد.
            $requested = substr_count($request->messages[1]['content'], 'prospect_key');

            return ['messages' => []];
        });

        $outcome = app(ProspectMessageService::class)->generate(
            $project, $many, MessageChannel::Whatsapp, MessageObjective::Trust, $user,
        );

        $this->assertSame(3, $outcome['skipped']);
        // السقف قبل الاستدعاء لا بعده: لا استعلام يُنفَق على من تجاوز الحد.
        $this->assertSame(ProspectMessageService::BATCH_LIMIT, $requested);
    }

    #[Test]
    public function a_prospect_the_model_skipped_is_named_and_never_invented(): void
    {
        [$user, $project] = $this->project();
        $prospects = $this->prospects($project, $user);
        $answered = $prospects->first();

        $this->fakeRunner(fn () => ['messages' => [[
            'prospect_key' => 'p'.$answered->id,
            'content' => 'رسالة مكتملة لهذا العميل وحده دون غيره من القائمة.',
            'why' => 'مبنية على ما دار بينكما.',
        ]]]);

        $outcome = app(ProspectMessageService::class)->generate(
            $project, $prospects, MessageChannel::Whatsapp, MessageObjective::Trust, $user,
        );

        $this->assertCount(1, $outcome['messages']);
        $this->assertSame([$prospects->last()->name], $outcome['failed']);
        $this->assertSame(1, ProspectMessage::count());
    }

    #[Test]
    public function regenerating_keeps_the_previous_message_as_a_parent(): void
    {
        [$user, $project] = $this->project();
        $prospect = $this->prospects($project, $user)->first();

        $this->fakeRunner(fn () => ['messages' => [[
            'prospect_key' => 'p'.$prospect->id,
            'content' => 'النسخة الأولى من رسالة هذا العميل قبل أي تعديل لاحق.',
            'why' => 'الأولى.',
        ]]]);
        app(ProspectMessageService::class)->generate(
            $project, collect([$prospect]), MessageChannel::Whatsapp, MessageObjective::Trust, $user,
        );

        $first = ProspectMessage::firstOrFail();

        $this->fakeRunner(fn () => ['messages' => [[
            'prospect_key' => 'p'.$prospect->id,
            'content' => 'النسخة الثانية بعد تغيّر ما نعرفه عن هذا العميل.',
            'why' => 'الثانية.',
        ]]]);
        app(ProspectMessageService::class)->generate(
            $project, collect([$prospect->fresh()]), MessageChannel::Whatsapp, MessageObjective::Trust, $user,
        );

        $second = ProspectMessage::latest('id')->firstOrFail();

        $this->assertSame($first->id, $second->parent_id);
        $this->assertSame(2, ProspectMessage::count());
        $this->assertNotSame($first->content, $second->fresh()->content);
    }

    #[Test]
    public function the_closest_persona_is_matched_by_city_and_interests(): void
    {
        [$user, $project] = $this->project();
        $panel = $this->panel($project);
        $service = app(PersonaMessageProfileService::class);

        $riyadh = $service->bestMatch($panel, 'الرياض', []);
        $jeddah = $service->bestMatch($panel, 'جدة', []);

        $this->assertNotSame($riyadh, $jeddah);
        // بلا تطابق تعود null: شخصية خاطئة تعطي نبرة خاطئة، والفراغ أصدق.
        $this->assertNull($service->bestMatch($panel, 'أبها', ['شيء لا يخص أحدًا']));
    }

    #[Test]
    public function the_page_lists_prospects_and_refuses_another_projects_records(): void
    {
        [$user, $project] = $this->project();
        $prospects = $this->prospects($project, $user);
        [, $other] = $this->project('مشروع آخر');

        $this->actingAs($user)
            ->get(route('app.prospects.index', $project))
            ->assertOk()
            ->assertSee($prospects->first()->name, false)
            ->assertSee('ولّد رسالة لكل عميل', false);

        // سجل من مشروع آخر لا يُقبل ولو مُرّر معرّفه.
        $this->actingAs($user)
            ->patch(route('app.prospects.update', [$other, $prospects->first()]), ['status' => 'archived'])
            ->assertNotFound();

        $this->assertSame(Prospect::STATUS_ACTIVE, $prospects->first()->fresh()->status);
    }

    #[Test]
    public function the_api_mirrors_the_web_surface(): void
    {
        [$user, $project] = $this->project();
        $this->prospects($project, $user);

        $this->actingAs($user)
            ->getJson(route('api.v1.prospects.index', $project))
            ->assertOk()
            ->assertJsonPath('data.batch_limit', ProspectMessageService::BATCH_LIMIT)
            ->assertJsonCount(2, 'data.prospects');
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(string $name = 'مشروع العملاء'): array
    {
        $user = User::first() ?? User::factory()->create();

        return [$user, app(ProjectService::class)->create($user, [
            'name' => $name,
            'industry' => 'التجارة الإلكترونية',
        ])];
    }

    private function panel(Project $project): PersonaPanel
    {
        return PersonaPanel::create([
            'project_id' => $project->id,
            'personas' => [
                ['name' => 'سارة', 'role' => 'صاحبة متجر', 'locations' => ['الرياض'],
                    'interests' => ['المنتجات اليدوية'], 'tone' => 'هادئة موثّقة',
                    'objection' => 'ما الذي يثبت أن هذا مختلف؟', 'spending_level' => 'متوسط'],
                ['name' => 'ماجد', 'role' => 'صاحب كافيه', 'locations' => ['جدة'],
                    'interests' => ['العروض'], 'tone' => 'صريحة بالأرقام',
                    'objection' => 'أجد أرخص منه.', 'spending_level' => 'منخفض'],
            ],
            'source' => 'rules',
            'generated_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, Prospect>
     */
    private function prospects(Project $project, User $user)
    {
        $panel = $project->personaPanel ?? $this->panel($project);
        $keys = array_keys(app(PersonaMessageProfileService::class)->profiles($panel));

        return collect([
            ['name' => 'أبو خالد', 'city' => 'الرياض', 'notes' => 'قابلته في معرض الرياض وسأل عن مدة التوصيل.'],
            ['name' => 'منى العتيبي', 'city' => 'جدة', 'notes' => 'طلبت عرض سعر ولم ترد بعدها.'],
        ])->map(fn (array $row, int $index) => Prospect::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'name' => $row['name'],
            'city' => $row['city'],
            'notes' => $row['notes'],
            'temperature' => 'warm',
            'preferred_channel' => 'whatsapp',
            'persona_key' => $keys[$index] ?? null,
            'status' => Prospect::STATUS_ACTIVE,
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
