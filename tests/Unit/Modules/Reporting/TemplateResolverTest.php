<?php

namespace Tests\Unit\Modules\Reporting;

use App\Models\Objective;
use App\Models\RecommendationTemplate;
use App\Models\TemplateGap;
use App\Modules\Reporting\Templates\TemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_and_binds_a_template_by_exact_objective(): void
    {
        $objective = Objective::create([
            'slug' => 'clarify-offer', 'name' => 'توضيح العرض',
            'domain' => 'offer', 'description' => 'صياغة عرض واضح.',
        ]);
        $template = RecommendationTemplate::create([
            'objective_id' => $objective->id,
            'kind' => 'page_outline',
            'title' => 'ورقة العرض',
            'body' => ['blocks' => [['label' => 'النشاط', 'value' => '{business_name}']]],
            'required_context' => ['business_name'],
            'locale' => 'ar', 'version' => 1, 'active' => true,
        ]);
        $template->bindings()->create([
            'field_key' => 'business_name', 'answer_key' => 'project.name', 'transform' => 'text',
        ]);

        $resolved = app(TemplateResolver::class)->forObjective('clarify-offer', [
            'project' => ['name' => 'متجر أفق'],
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame('clarify-offer', $resolved->objectiveId);
        $this->assertSame('page_outline', $resolved->kind);
        $this->assertSame('متجر أفق', $resolved->blocks[0]['value']);
        $this->assertSame($template->id, $resolved->templateId);
    }

    #[Test]
    public function it_never_uses_a_same_kind_template_from_another_objective(): void
    {
        $wrong = Objective::create([
            'slug' => 'build-content-engine', 'name' => 'محرك المحتوى',
            'domain' => 'content', 'description' => 'إنشاء محتوى.',
        ]);
        RecommendationTemplate::create([
            'objective_id' => $wrong->id, 'kind' => 'checklist', 'title' => 'قائمة محتوى',
            'body' => ['blocks' => []], 'required_context' => [], 'locale' => 'ar',
            'version' => 1, 'active' => true,
        ]);
        $requested = Objective::create([
            'slug' => 'competitor-analysis', 'name' => 'تحليل منافس',
            'domain' => 'competition', 'description' => 'تحليل المنافس.',
        ]);

        $this->assertNull(app(TemplateResolver::class)->forObjective(
            $requested->slug,
            ['preferred_kind' => 'checklist'],
        ));
        $this->assertDatabaseHas('template_gaps', [
            'objective_id' => $requested->id, 'occurrences' => 1,
        ]);
    }

    #[Test]
    public function missing_required_context_degrades_instead_of_inventing_a_value(): void
    {
        $objective = Objective::create([
            'slug' => 'define-audience', 'name' => 'تحديد الجمهور',
            'domain' => 'audience', 'description' => 'تحديد الجمهور.',
        ]);
        $template = RecommendationTemplate::create([
            'objective_id' => $objective->id, 'kind' => 'checklist', 'title' => 'بطاقة العميل',
            'body' => ['blocks' => [['label' => 'الجمهور', 'value' => '{audience}']]],
            'required_context' => ['audience'], 'locale' => 'ar', 'version' => 1, 'active' => true,
        ]);
        $template->bindings()->create([
            'field_key' => 'audience', 'answer_key' => 'answers.target_audience', 'transform' => 'text',
        ]);

        $this->assertNull(app(TemplateResolver::class)->forObjective('define-audience', ['answers' => []]));
        $gap = TemplateGap::firstOrFail();
        $this->assertSame(['audience'], $gap->missing_context);
    }
}
