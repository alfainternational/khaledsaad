<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use App\Modules\Measurement\QueryBudgetManager;
use App\Services\Projects\ProjectService;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use App\Support\Settings\SettingsConfig;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * القدرة التي لا يبلغها مستخدم غير موجودة — ولو كانت مبنيّة ومنشورة وخضراء.
 *
 * `ArchitectureBoundariesTest` يحرس أن لكل خدمة مستدعيًا في كود الإنتاج. هذا
 * الملف يحرس الطبقة التي تليها: أن لكل مخرَج **مدخلًا في الواجهة**. العطل
 * الذي وُجد من أجله حقيقي وتكرّر:
 *
 * - `app.presence.show` و`app.portfolio` — مخرَجان من المرحلة ٣، منشوران على
 *   الإنتاج، وبلا رابط واحد في الواجهة كلها. أغلى قدرة تشغيليًّا وأهم طبقة
 *   تجاريًّا، ولا طريق إليهما إلا كتابة المسار يدويًّا.
 * - سقف الاستعلامات — حجزٌ وتنبيهٌ ورفض، ولا يراه المسؤول عن التكلفة.
 * - مفاتيح النسخ الصوتي — `config/services.php` يعلن أنها «تُضبط من لوحة
 *   الآدمن»، ولم تكن في كتالوج اللوحة.
 */
class CapabilityReachabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_presence_report_is_reachable_from_the_project_screen(): void
    {
        [$user, $project] = $this->entitledProject(FeatureKey::DIAGNOSIS_FULL);

        $this->actingAs($user)
            ->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee(route('app.presence.show', $project), false);
    }

    #[Test]
    public function the_agency_board_is_reachable_from_the_navigation(): void
    {
        [$user, $project] = $this->entitledProject(FeatureKey::REPORTS_AGENCY);

        $this->actingAs($user)
            ->get(route('app.projects.show', $project))
            ->assertOk()
            ->assertSee(route('app.portfolio'), false);
    }

    /**
     * البوابة على المسار، والواجهة تقولها بلغة المستخدم لا بصمت.
     *
     * إخفاء الرابط بلا بديل يجعل الميزة غير موجودة عند من لا يملكها، ولا سبب
     * لديه للترقية — وهو ما تمنعه §٦.
     */
    #[Test]
    public function an_unentitled_workspace_is_told_where_the_report_lives(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        app(Entitlements::class)->flush();

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);

        $response = $this->actingAs($user)
            ->get(route('app.projects.show', $project->fresh()))
            ->assertOk();

        $response->assertSee('حضورك في الإجابات');
        $response->assertDontSee(route('app.presence.show', $project->fresh()), false);
    }

    #[Test]
    public function the_operational_cap_is_visible_to_whoever_answers_for_the_cost(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = app(ProjectService::class)->create($admin, ['name' => 'نشاط يستهلك']);

        $workspace = $project->workspace;
        $workspace->forceFill(['monthly_query_limit' => 10])->save();

        // ثمانية من عشرة: فوق عتبة التنبيه وتحت التوقف.
        app(QueryBudgetManager::class)->reserve($workspace, 8, 'presence');

        $response = $this->actingAs($admin)
            ->get(route('admin.usage'))
            ->assertOk();

        $response->assertSee('سقوف الاستعلامات');
        // الرقم مع أساسه لا وحده (§١٣).
        $response->assertSee('8 من 10');
        $response->assertSee('نُبِّهت عند ٨٠٪');
    }

    #[Test]
    public function the_speech_keys_can_be_set_from_the_admin_panel(): void
    {
        $keys = collect(SettingsConfig::fields())->pluck('key');

        foreach ([
            'services.speech.key',
            'services.speech.base_url',
            'services.speech.model',
            'services.speech.cost_per_minute',
        ] as $key) {
            $this->assertTrue(
                $keys->contains($key),
                "المفتاح {$key} مقروء من config ولا مكان في اللوحة يضبطه.",
            );
        }
    }

    /**
     * بلا تكلفة الدقيقة يعمل النسخ ويُسجَّل الإنفاق صفرًا — السقف يعدّ
     * المواضع ويكذب تقرير التكلفة. فالمفتاح يجب أن يسري حيًّا لا أن يُعرض.
     */
    #[Test]
    public function saving_the_speech_cost_applies_live_to_config(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                // النقاط تصل النموذج بـ`__`: اسم حقل HTML لا يحمل نقطة.
                'services__speech__cost_per_minute' => '0.006',
                'services__speech__model' => 'whisper-large-v3',
            ])
            ->assertRedirect(route('admin.settings'));

        app(SettingsConfig::class)->apply();

        $this->assertSame(0.006, (float) config('services.speech.cost_per_minute'));
        $this->assertSame('whisper-large-v3', config('services.speech.model'));
    }

    /**
     * @return array{0: User, 1: \App\Models\Project}
     */
    private function entitledProject(string $feature): array
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);

        PlanFeature::updateOrCreate(
            [
                'plan_id' => Plan::where('key', 'free')->value('id'),
                'feature_id' => Feature::where('key', $feature)->value('id'),
            ],
            ['enabled' => true, 'value' => null],
        );

        app(Entitlements::class)->flush();

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);

        return [$user, $project->fresh()];
    }
}
