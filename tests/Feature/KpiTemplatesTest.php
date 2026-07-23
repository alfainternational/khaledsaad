<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Projects\ProjectService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المؤشرات النموذجية: صفحة المشروع تعرض أمثلة يفهم منها المستخدم المقصود
 * ويبدأ بضغطة، بدل نموذج فارغ بلا سياق.
 */
class KpiTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);
    }

    #[Test]
    public function the_project_page_offers_template_kpis_in_customer_language(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر تجريبي']);

        $this->actingAs($user)
            ->get(route('app.projects.show', $project->slug))
            ->assertOk()
            ->assertSee('نماذج جاهزة')
            ->assertSee('المبيعات الشهرية')
            ->assertSee('تكلفة جلب العميل')
            // لغة العميل: يشرح ما يقيسه لا مصطلحًا مجردًا.
            ->assertSee('المقياس الأهم لأي مشروع', false);
    }

    #[Test]
    public function choosing_a_template_still_creates_a_normal_kpi(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر']);

        // ما يملؤه النموذج هو ما يُرسَل: اسم ووحدة وأرقام المستخدم.
        $this->actingAs($user)
            ->post(route('app.kpis.store', $project->slug), [
                'name' => 'المبيعات الشهرية',
                'unit' => 'ريال',
                'baseline' => 20000,
                'target' => 35000,
            ])
            ->assertRedirect();

        $kpi = $project->kpis()->firstOrFail();
        $this->assertSame('المبيعات الشهرية', $kpi->name);
        $this->assertSame(20000.0, $kpi->baseline);
        $this->assertSame(35000.0, $kpi->target);
    }
}
