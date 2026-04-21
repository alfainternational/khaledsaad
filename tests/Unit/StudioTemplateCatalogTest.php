<?php

namespace Tests\Unit;

use App\Support\AI\StudioTemplateCatalog;
use App\Support\AI\StudioTemplateContractRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioTemplateCatalogTest extends TestCase
{
    #[Test]
    public function it_exposes_one_shared_source_for_seeded_templates_and_runtime_definitions(): void
    {
        $catalog = new StudioTemplateCatalog;
        $registry = new StudioTemplateContractRegistry;

        $seededTemplates = collect($catalog->seededTemplates())->keyBy('code');
        $definitions = $catalog->definitions();

        $this->assertCount(10, $seededTemplates);
        $this->assertSame($seededTemplates->keys()->all(), array_keys($definitions));
        $this->assertFalse($seededTemplates->contains(fn (array $template): bool => array_key_exists('registry_definition', $template)));

        $positioningTemplate = $seededTemplates->get('brand-positioning');
        $positioningDefinition = $registry->definitionFor('brand-positioning');
        $brandPackTemplate = $seededTemplates->get('brand-full-pack');

        $this->assertStringContainsString('Elevator pitch', $positioningTemplate['prompt_template']);
        $this->assertStringContainsString('Unique Mechanism', $positioningTemplate['prompt_template']);
        $this->assertContains('Framework', $definitions['brand-positioning']['required_fragments']);
        $this->assertContains('تحسين الحضور', $definitions['brand-positioning']['generic_red_flags']);
        $this->assertSame(
            ['positioning_signal', 'offer_signal', 'human_signal'],
            $definitions['brand-positioning']['strict_blocking_context']
        );
        $this->assertContains('Commodity', $definitions['brand-positioning']['quality_needs_input_issue_markers']);
        $this->assertContains(
            'الـ Unique Mechanism يجب أن يكون طريقة عمل تشغيلية أو منطق قرار أو نموذج تنفيذ، لا مجرد اسم خدمة مثل "إدارة محتوى" أو "شراكة نمو".',
            $definitions['brand-positioning']['strategic_requirements']
        );
        $this->assertStringContainsString('Boundary', implode(' | ', $brandPackTemplate['output_contract_json']['sections'] ?? []));
        $this->assertContains('diagnosis_signal', $definitions['brand-full-pack']['strict_blocking_context']);
        $this->assertSame(
            $definitions['brand-positioning'] + ['code' => 'brand-positioning'],
            $positioningDefinition
        );
    }
}
