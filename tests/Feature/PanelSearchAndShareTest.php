<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PanelSearchAndShareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_returns_only_what_the_user_owns(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        app(ProjectService::class)->create($user, ['name' => 'متجر عسل سدر']);
        app(ProjectService::class)->create($stranger, ['name' => 'متجر عسل الغريب']);

        $this->actingAs($user)
            ->get(route('app.search', ['q' => 'عسل']))
            ->assertOk()
            ->assertSee('متجر عسل سدر')
            ->assertDontSee('متجر عسل الغريب');
    }

    #[Test]
    public function a_signed_share_link_opens_the_report_without_auth_and_expires(): void
    {
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع للمشاركة']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $svc = app(ToolRunService::class);
        $run = $svc->start($project, $tool, $user);
        $svc->saveStep($run, 1, ['business_model' => 'services', 'description' => str_repeat('وصف واضح للخدمة ', 3), 'geography' => 'الرياض', 'monthly_budget' => 5000]);

        $finding = fn (string $title) => [
            'title' => $title,
            'description' => 'شرح واضح لهذه النتيجة مبني على إجابات صاحب المشروع نفسه.',
            'severity' => 'high',
            'is_assumption' => false,
            'recommendations' => [[
                'title' => 'خطوة تنفيذية واضحة',
                'description' => 'نفّذ هذه الخطوة خلال هذا الأسبوع بشكل محدد وقابل للقياس.',
                'impact' => 'high',
                'effort' => 'low',
            ]],
        ];

        $report = app(ManualReportService::class)->import($run->fresh(), [
            'summary' => 'ملخص تنفيذي مكتوب بلغة صاحب المشروع يوضح أين هو الآن وما الذي يبدأ به فورًا.',
            'next_step' => ['title' => 'ابدأ بربط القياس', 'description' => 'عرّف حدث الشراء واربطه بمصدر الزيارة قبل أي زيادة في الإنفاق.'],
            'findings' => [$finding('نتيجة أولى'), $finding('نتيجة ثانية'), $finding('نتيجة ثالثة')],
        ], User::factory()->create(['is_admin' => true]));

        $url = URL::temporarySignedRoute('shared.report', now()->addDays(7), ['report' => $report->id]);

        // بلا توقيع: مرفوض. بالتوقيع: يفتح بلا حساب.
        $this->get(route('shared.report', ['report' => $report->id]))->assertForbidden();
        $this->get($url)->assertOk()->assertSee('نسخة للاطلاع فقط')->assertSee('نتيجة أولى');
    }
}
