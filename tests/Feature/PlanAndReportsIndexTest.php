<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * القسمان اللذان كانت الملاحة تعد بهما وتوجّه إلى غيرهما.
 *
 * «الخطة والمهام» و«التقارير» كانا يشيران إلى صفحة المشاريع، فيقرأ
 * المستخدم قائمةً بثلاثة أبواب تفتح كلها على باب واحد. الاختبار هنا
 * يثبت أن للقسمين وجهةً خاصةً بهما تُعرض فعلًا.
 */
class PlanAndReportsIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_plan_section_has_its_own_destination(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('app.plan'))
            ->assertOk()
            ->assertSee('الخطة والمهام');
    }

    #[Test]
    public function the_reports_section_has_its_own_destination(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('app.reports.index'))
            ->assertOk()
            ->assertSee('تقاريري');
    }

    /**
     * حالة الفراغ تشرح وتقود إلى إجراء واحد — لا «٠ مهام» وحدها.
     */
    #[Test]
    public function an_empty_plan_explains_what_will_appear_and_offers_one_action(): void
    {
        $user = $this->user();
        $this->project($user);

        $this->actingAs($user)
            ->get(route('app.plan'))
            ->assertOk()
            ->assertSee('لا مهام بعد')
            ->assertSee('مهام مقترحة');
    }

    private function user(): User
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);

        return User::factory()->create();
    }

    private function project(User $user): Project
    {
        return app(ProjectService::class)->create($user, ['name' => 'مشروع اختبار']);
    }
}
