<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Execution\ReviewQueue;
use App\Services\Billing\CreditManager;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ManualReportService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * طابور المراجعة البشرية ومدّته المعلنة.
 *
 * ما تحرسه: ألّا يصير أقوى ما يميّز المنتج أسرعَ ما يفقد الثقة. الوعد
 * المكسور أسوأ من غياب الوعد.
 */
class ReviewQueueSlaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_full_queue_stops_accepting_instead_of_promising_what_it_cannot_keep(): void
    {
        config(['review.max_open' => 1]);

        $this->requestReview();

        $this->expectException(ValidationException::class);
        $this->requestReview();
    }

    /**
     * والرفض يقول ما يستطيعه المستخدم الآن، لا «حدث خطأ».
     */
    #[Test]
    public function the_refusal_offers_the_automatic_path_instead(): void
    {
        config(['review.max_open' => 1]);
        $this->requestReview();

        try {
            $this->requestReview();
            $this->fail('قُبل طلبٌ فوق السقف.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->implode(' ');

            $this->assertStringContainsString('ممتلئ', $message);
            $this->assertStringContainsString('التحليل التلقائي', $message);
        }
    }

    /**
     * سقفٌ صفر يعني «بلا سقف» لا «لا تقبل شيئًا».
     */
    #[Test]
    public function a_zero_cap_means_unlimited_not_closed(): void
    {
        config(['review.max_open' => 0]);

        $this->assertTrue(app(ReviewQueue::class)->isAcceptingRequests());
    }

    #[Test]
    public function a_request_older_than_the_sla_is_counted_as_breached(): void
    {
        config(['review.sla_hours' => 48, 'review.max_open' => 0]);

        $run = $this->requestReview();
        $this->assertSame(0, app(ReviewQueue::class)->breachedCount());

        $run->forceFill(['updated_at' => now()->subHours(72)])->saveQuietly();

        $this->assertSame(1, app(ReviewQueue::class)->breachedCount());
    }

    #[Test]
    public function the_queue_warns_before_it_fills_not_after(): void
    {
        config(['review.max_open' => 5, 'review.warn_ratio' => 0.8]);

        foreach (range(1, 4) as $ignored) {
            $this->requestReview();
        }

        $status = app(ReviewQueue::class)->status();

        $this->assertTrue($status['crowded'], 'لم يُحذَّر قبل الامتلاء.');
        $this->assertTrue($status['accepting'], 'أُوقف الاستقبال قبل بلوغ السقف.');
    }

    #[Test]
    public function a_delivered_review_leaves_the_queue(): void
    {
        config(['review.max_open' => 0]);

        $run = $this->requestReview();
        $this->assertSame(1, app(ReviewQueue::class)->openCount());

        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED])->save();

        $this->assertSame(0, app(ReviewQueue::class)->openCount());
    }

    private function requestReview(): ToolRun
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع '.uniqid()]);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        app(CreditManager::class)->walletFor($project->workspace)
            ->forceFill(['balance' => 500])->save();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        return app(ManualReportService::class)->requestManualReview($run, allowIncomplete: true);
    }
}
