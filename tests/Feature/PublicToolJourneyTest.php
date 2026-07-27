<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الرحلة العامة: الرئيسية ← الأدوات ← أداة ← تسجيل ← مشروع ← أول خطوة.
 * الاختبار يحرس الاتصال نفسه، لا شكل الصفحات: أي حلقة مقطوعة تسقط هنا.
 */
class PublicToolJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_home_page_links_to_the_real_catalog_and_to_a_real_starting_point(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee(route('tools.index'), false);
        $response->assertSee(route('tools.show', 'marketing-score'), false);
        $response->assertSee(route('register', ['tool' => 'marketing-score']), false);
        $response->assertSee(route('login'), false);

        // أداة معروضة من قاعدة البيانات لا من نص ثابت في الصفحة.
        $response->assertSee(Tool::where('key', 'marketing-score')->value('title'));

        // لا وعد بتجربة كضيف ما دامت غير مبنية.
        $response->assertDontSee('ابدأ كضيف واحفظ لاحقًا');
    }

    #[Test]
    public function a_visitor_can_browse_the_catalog_without_an_account(): void
    {
        $response = $this->get(route('tools.index'))->assertOk();

        $this->assertSame(11, Tool::count());
        $response->assertSee('data-layout="marketing"', false);
        $response->assertSee('public-card-grid', false);
        $response->assertSee('ما الذي تريد فهمه أو تحسينه الآن؟');
        $response->assertSee('قريبًا');
        $response->assertSee(route('tools.show', 'funnel-audit'), false);
    }

    #[Test]
    public function a_tool_page_shows_its_questions_and_outputs_before_registration(): void
    {
        $response = $this->get(route('tools.show', 'marketing-score'))->assertOk();

        $response->assertSee('data-layout="marketing"', false);
        $response->assertSee('public-tool-hero', false);
        $response->assertSee('public-step-grid', false);
        $response->assertSee('المعلومات التي ستحتاج إليها');
        $response->assertSee(route('register', ['tool' => 'marketing-score']), false);
        $response->assertSee(route('login', ['tool' => 'marketing-score']), false);
    }

    #[Test]
    public function a_tool_that_is_not_built_yet_says_so_instead_of_offering_a_dead_button(): void
    {
        // أداة معروضة بلا إصدار منشور. تُنشأ هنا حتى يبقى السلوك مغطى
        // مهما اكتملت أدوات الكتالوج لاحقًا.
        $tool = $this->comingSoonTool();

        $response = $this->get(route('tools.show', $tool->key))->assertOk();

        $response->assertSee('غير متاح حاليًا');
        $response->assertDontSee(route('register', ['tool' => $tool->key]), false);
    }

    #[Test]
    public function registering_from_a_tool_carries_the_intent_into_the_project_form(): void
    {
        $this->get(route('register', ['tool' => 'marketing-score']))
            ->assertOk()
            ->assertSee(Tool::where('key', 'marketing-score')->value('title'));

        $this->post(route('register'), [
            'name' => 'خالد',
            'email' => 'journey@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('app.projects.create', ['tool' => 'marketing-score']));

        $this->get(route('app.projects.create', ['tool' => 'marketing-score']))
            ->assertOk()
            ->assertSee(Tool::where('key', 'marketing-score')->value('title'));
    }

    #[Test]
    public function creating_the_project_opens_the_chosen_tool_on_its_first_step(): void
    {
        $this->get(route('register', ['tool' => 'campaign-planner']))->assertOk();

        $this->post(route('register'), [
            'name' => 'خالد',
            'email' => 'journey2@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ]);

        $response = $this->post(route('app.projects.store'), [
            'name' => 'متجر تجريبي',
            'start_tool' => 'campaign-planner',
        ]);

        $run = ToolRun::latest('id')->firstOrFail();

        $response->assertRedirect(route('app.runs.step', [$run, 1]));
        $this->assertSame('campaign-planner', $run->toolVersion->tool->key);

        // النية تُستهلك مرة واحدة: مشروع ثانٍ لا يُعاد توجيهه إلى الأداة نفسها.
        $second = $this->post(route('app.projects.store'), ['name' => 'مشروع ثانٍ']);
        $second->assertRedirect();
        $this->assertStringNotContainsString('/runs/', $second->headers->get('Location'));
        $this->assertSame(1, ToolRun::count());
    }

    #[Test]
    public function logging_in_from_a_tool_lands_on_that_tool(): void
    {
        User::factory()->create([
            'email' => 'back@example.test',
            'password' => 'password-1234',
        ]);

        $this->get(route('login', ['tool' => 'content-engine']))
            ->assertOk()
            ->assertSee('data-layout="auth"', false)
            ->assertSee('layout-page--auth', false);

        $this->post(route('login'), [
            'email' => 'back@example.test',
            'password' => 'password-1234',
        ])->assertRedirect(route('app.tools.show', 'content-engine'));
    }

    #[Test]
    public function an_unknown_or_unbuilt_tool_intent_is_ignored_rather_than_breaking_the_flow(): void
    {
        $this->get(route('register', ['tool' => $this->comingSoonTool()->key]))->assertOk();

        $this->post(route('register'), [
            'name' => 'خالد',
            'email' => 'journey3@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('app.dashboard'));
    }

    private function comingSoonTool(): Tool
    {
        return Tool::create([
            'key' => 'not-open-yet',
            'name' => 'Not Open Yet',
            'title' => 'أداة لم تفتح بعد',
            'description' => 'معروضة بحالتها الصريحة ولا تقبل التشغيل.',
            'category' => 'اختبار',
            'status' => Tool::STATUS_COMING_SOON,
            'sort_order' => 99,
        ]);
    }
}
