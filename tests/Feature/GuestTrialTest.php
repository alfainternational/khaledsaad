<?php

namespace Tests\Feature;

use App\Models\GuestSession;
use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\Workspace;
use App\Services\Guests\GuestSessionManager;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التجربة قبل الحساب: يجرّب الزائر فعلًا، ثم يقرر.
 * وحين يسجّل، لا يفقد شيئًا مما كتبه.
 */
class GuestTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    /**
     * المتصفح يحمل ملف تعريف الارتباط بين الطلبات؛ عميل الاختبار لا يفعل ذلك تلقائيًا.
     */
    private function startTrial(string $toolKey = 'marketing-score'): ToolRun
    {
        $response = $this->post(route('try.start', $toolKey));
        $cookie = $response->getCookie(GuestSessionManager::COOKIE, false);

        if ($cookie !== null) {
            // نفك التشفير للحصول على القيمة الأصلية؛ عميل الاختبار يعيد تشفيرها عند الإرسال.
            $plain = CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
            $this->withCookie(GuestSessionManager::COOKIE, $plain);
        }

        return ToolRun::firstOrFail();
    }

    #[Test]
    public function a_visitor_starts_a_real_run_without_an_account(): void
    {
        $response = $this->post(route('try.start', 'marketing-score'));

        $run = ToolRun::firstOrFail();

        $response->assertRedirect(route('try.step', [$run, 1]));
        $this->assertGuest();
        $this->assertNotNull($run->guest_session_id);
        $this->assertNull($run->user_id);

        // مساحة عمل بلا صاحب حساب حتى الآن.
        $this->assertTrue(Workspace::firstOrFail()->isGuest());
    }

    #[Test]
    public function the_visitor_answers_and_reaches_a_result_page(): void
    {
        $run = $this->startTrial();

        $this->get(route('try.step', [$run, 1]))
            ->assertOk()
            ->assertSee('يمكنك المتابعة الآن من دون حساب');

        $this->post(route('try.step.save', [$run, 1]), [
            'business_model' => 'services',
            'description' => 'خدمة استشارات تسويقية للمتاجر الصغيرة داخل المدينة.',
            'geography' => 'الرياض',
        ])->assertRedirect(route('try.step', [$run, 2]));

        $this->assertDatabaseHas('tool_run_answers', ['tool_run_id' => $run->id, 'field_key' => 'geography']);
    }

    #[Test]
    public function another_visitor_cannot_open_someone_elses_trial(): void
    {
        $this->post(route('try.start', 'marketing-score'));
        $run = ToolRun::firstOrFail();

        // متصفح آخر لا يحمل ملف تعريف الارتباط نفسه: لا يرى تجربة غيره.
        $this->get(route('try.step', [$run, 1]))->assertNotFound();
    }

    #[Test]
    public function registering_moves_the_whole_trial_into_the_new_account(): void
    {
        $run = $this->startTrial();

        $this->post(route('try.step.save', [$run, 1]), [
            'business_model' => 'services',
            'description' => 'خدمة استشارات تسويقية للمتاجر الصغيرة داخل المدينة.',
            'geography' => 'الرياض',
        ]);

        $this->post(route('register'), [
            'name' => 'خالد',
            'email' => 'guest@example.test',
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect(route('app.runs.review', $run));

        $this->assertAuthenticated();

        $user = auth()->user();
        $workspace = Workspace::firstOrFail();

        // المساحة نفسها انتقلت: لا نسخة، ولا رابط مكسور.
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertFalse($workspace->isGuest());
        $this->assertSame($user->id, GuestSession::firstOrFail()->claimed_by);
        $this->assertSame($user->id, $run->fresh()->user_id);

        // وما كتبه أثناء التجربة صار معلومًا في ملف مشروعه.
        $this->assertSame('الرياض', Project::firstOrFail()->profile->geography);

        // ويستطيع فتح ما جرّبه داخل حسابه.
        $this->get(route('app.runs.review', $run))->assertOk();
    }

    #[Test]
    public function a_tool_that_is_not_built_yet_cannot_be_tried(): void
    {
        $tool = Tool::create([
            'key' => 'not-open-yet',
            'name' => 'Not Open Yet',
            'title' => 'أداة لم تفتح بعد',
            'description' => 'معروضة بحالتها الصريحة ولا تقبل التشغيل.',
            'category' => 'اختبار',
            'status' => Tool::STATUS_COMING_SOON,
            'sort_order' => 99,
        ]);

        $this->post(route('try.start', $tool->key))
            ->assertRedirect(route('tools.show', $tool->key));

        $this->assertSame(0, ToolRun::count());
    }
}
