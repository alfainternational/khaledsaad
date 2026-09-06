<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use App\Support\Failures\FailureKind;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * شاشة الفشل كما يراها المستخدم.
 *
 * ما تحرسه: ألّا تعود الشاشة التي بذل فيها مستخدمٌ ستين سؤالًا ثم قرأ
 * رسالة استثناءٍ خامّة تتّهم رصيده بعطلٍ لدينا، فوقها شريط تقدّم يقول
 * إن شيئًا لا يزال يجري.
 */
class RunFailureScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_failure_that_is_ours_never_shows_the_raw_exception_or_blames_the_users_credit(): void
    {
        [$user, $run] = $this->failedRun(
            kind: FailureKind::Ours,
            detail: 'DeepSeek API error 402: insufficient balance for account',
        );

        $response = $this->actingAs($user)->get(route('app.runs.status', $run->uuid))->assertOk();

        $response->assertSee('السبب لدينا لا لديك');
        $response->assertSee('لم يُخصم من رصيدك شيء');

        // لا تسرّب لنص الاستثناء ولا لاسم المزوّد ولا لرمز حالة HTTP.
        $response->assertDontSee('DeepSeek');
        $response->assertDontSee('insufficient balance');
        $response->assertDontSee('402');

        // ولا تُعرض لغة الرصيد على عطلٍ ليس منه.
        $response->assertDontSee('رصيدك غير كافٍ');
    }

    /**
     * A4: الشاشة لا تناقض نفسها — لا تقدّم يجري فوق فشلٍ وقع.
     */
    #[Test]
    public function a_failed_run_does_not_show_a_progress_bar_above_the_failure(): void
    {
        [$user, $run] = $this->failedRun(FailureKind::Ours, 'انهيار داخلي');

        $this->actingAs($user)
            ->get(route('app.runs.status', $run->uuid))
            ->assertOk()
            // العنصر نفسه غائب من الصفحة. (اسمه يبقى في نصّ الاستطلاع،
            // وهو محروس بفحص وجود فلا يرمي حين تُخفى الشاشة شريطها.)
            ->assertDontSee('id="run-progress-bar"', false)
            ->assertDontSee('class="progress"', false)
            ->assertDontSee('ويصلك إشعار عند اكتماله');
    }

    /**
     * الحدّ الذي يملكه المستخدم وحده يعرض له بابًا يطرقه.
     */
    #[Test]
    public function only_a_users_own_limit_shows_the_billing_door(): void
    {
        // الفحص على شاشة الفشل نفسها لا على الصفحة كلها: رابط الفوترة
        // موجود في قائمة الحساب دائمًا، والسؤال هنا هل تطلب *رسالة العطل*
        // من المستخدم إجراءً أم لا.
        [$user, $ours] = $this->failedRun(FailureKind::Ours, 'عطل مزوّد');
        $this->actingAs($user)
            ->get(route('app.runs.status', $ours->uuid))
            ->assertOk()
            ->assertDontSee('الاشتراك والفوترة</a>', false);

        [$other, $theirs] = $this->failedRun(FailureKind::Theirs, 'رصيد');
        $this->actingAs($other)
            ->get(route('app.runs.status', $theirs->uuid))
            ->assertOk()
            ->assertSee('الاشتراك والفوترة</a>', false);
    }

    /**
     * @return array{0: User, 1: ToolRun}
     */
    private function failedRun(FailureKind $kind, string $detail): array
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الفشل']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        $run->forceFill([
            'status' => ToolRun::STATUS_FAILED,
            'failure_kind' => $kind->value,
            'failure_code' => $kind === FailureKind::Ours ? 'provider_unavailable' : 'insufficient_credits',
            'failure_reason' => $kind === FailureKind::Ours
                ? 'إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. سنشغّل التحليل تلقائيًا فور عودة الخدمة ونُشعرك.'
                : 'تشغيل هذه الأداة يكلّف 5 أرصدة، ورصيدك لا رصيد.',
            'failure_detail' => $detail,
            'completed_at' => now(),
        ])->save();

        return [$user, $run->refresh()];
    }
}
