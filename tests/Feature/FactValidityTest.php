<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\Tool;
use App\Models\User;
use App\Modules\Brain\ProjectKnowledgeService;
use App\Modules\Intake\FactValidity;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ProjectAnswerMemory;
use App\Services\Tools\ToolRunService;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * صلاحية الحقائق.
 *
 * ما تحرسه: أن قاعدة الحقائق لا تتحول من مكسبٍ إلى عطل. أن تسأل مرة
 * واحدة نعمة، وأن تُثبّت الجواب إلى الأبد كارثةٌ صامتة — التشخيص يُبنى
 * على رقمٍ لم يعد صحيحًا، ولا شيء يشتكي.
 */
class FactValidityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function facts_that_define_the_business_never_expire(): void
    {
        $validity = app(FactValidity::class);

        foreach (['name', 'business_model', 'sector', 'industry'] as $key) {
            $this->assertNull($validity->lifetimeDays($key), "الحقل {$key} لا يجب أن يتقادم.");
            $this->assertNull($validity->expiresAt($key));
        }
    }

    /**
     * الأرقام المتحرّكة تتقادم أسرع من الوصف الاستراتيجي.
     */
    #[Test]
    public function moving_numbers_expire_sooner_than_slow_descriptions(): void
    {
        $validity = app(FactValidity::class);

        $this->assertSame(30, $validity->lifetimeDays('monthly_traffic'));
        $this->assertSame(90, $validity->lifetimeDays('monthly_budget'));
        $this->assertSame(180, $validity->lifetimeDays('target_audience'));
    }

    #[Test]
    public function a_recorded_fact_carries_its_expiry(): void
    {
        $project = $this->project();

        app(ProjectKnowledgeService::class)
            ->record($project, 'monthly_budget', 4000, 'tool');

        $answer = ProjectAnswer::where('field_key', 'monthly_budget')->firstOrFail();

        $this->assertNotNull($answer->confirmed_at);
        $this->assertNotNull($answer->valid_until);
        $this->assertTrue($answer->valid_until->isFuture());
    }

    /**
     * الحقيقة المنتهية تُملأ ولا تُعدّ معروفة — فيُعرض سؤالها للتأكيد.
     */
    #[Test]
    public function a_stale_fact_is_prefilled_but_still_asked(): void
    {
        $project = $this->project();
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $field = $tool->currentVersion->fields->first();

        ProjectAnswer::create([
            'project_id' => $project->id,
            'field_key' => $field->key,
            'value_json' => ['value' => 'قيمة قديمة'],
            'confirmed_at' => now()->subYear(),
            'valid_until' => now()->subMonth(),
        ]);

        $run = app(ToolRunService::class)->start($project, $tool, $project->workspace->owner);
        $known = app(ProjectAnswerMemory::class)->prefill($run);

        $this->assertNotContains(
            $field->key,
            $known,
            'حقيقة منتهية عُدّت معروفة، فدخلت التشخيص بلا أن يراها صاحبها.',
        );

        // ومع ذلك القيمة محفوظة في التشغيل: التأكيد أقلّ احتكاكًا من الكتابة.
        $this->assertDatabaseHas('tool_run_answers', [
            'tool_run_id' => $run->id,
            'field_key' => $field->key,
        ]);
    }

    #[Test]
    public function a_fresh_fact_is_counted_as_known(): void
    {
        $project = $this->project();
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $field = $tool->currentVersion->fields->first();

        ProjectAnswer::create([
            'project_id' => $project->id,
            'field_key' => $field->key,
            'value_json' => ['value' => 'قيمة حديثة'],
            'confirmed_at' => now(),
            'valid_until' => now()->addMonths(3),
        ]);

        $run = app(ToolRunService::class)->start($project, $tool, $project->workspace->owner);
        $known = app(ProjectAnswerMemory::class)->prefill($run);

        $this->assertContains($field->key, $known);
    }

    #[Test]
    public function confirming_a_fact_renews_it_without_rewriting_it(): void
    {
        $project = $this->project();

        $answer = ProjectAnswer::create([
            'project_id' => $project->id,
            'field_key' => 'monthly_budget',
            'value_json' => ['value' => 4000],
            'confirmed_at' => now()->subYear(),
            'valid_until' => now()->subMonth(),
        ]);

        app(FactValidity::class)->confirm($answer);

        $this->assertTrue($answer->refresh()->valid_until->isFuture());
        $this->assertSame(4000, $answer->value_json['value'], 'التأكيد غيّر القيمة، وهو تأكيد لا تحرير.');
    }

    #[Test]
    public function stale_facts_are_listed_for_confirmation(): void
    {
        $project = $this->project();

        ProjectAnswer::create([
            'project_id' => $project->id, 'field_key' => 'monthly_budget',
            'value_json' => ['value' => 1], 'valid_until' => now()->subDay(),
        ]);
        ProjectAnswer::create([
            'project_id' => $project->id, 'field_key' => 'target_audience',
            'value_json' => ['value' => 2], 'valid_until' => now()->addYear(),
        ]);

        $stale = app(FactValidity::class)->stale($project);

        $this->assertCount(1, $stale);
        $this->assertSame('monthly_budget', $stale->first()->field_key);
    }

    private function project(): Project
    {
        $this->seed(PlanSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ToolCatalogSeeder::class);

        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, ['name' => 'مشروع الصلاحية']);
    }
}
