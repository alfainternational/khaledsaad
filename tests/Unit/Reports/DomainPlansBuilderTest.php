<?php

namespace Tests\Unit\Reports;

use App\Application\Reports\DomainPlansBuilder;
use App\Domain\Tool\Models\ToolRun;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainPlansBuilderTest extends TestCase
{
    private function runs(array $toolInputs): Collection
    {
        return collect($toolInputs)->map(function (array $inputs, string $code): ToolRun {
            $run = new ToolRun;
            $run->tool_code = $code;
            $run->inputs_json = $inputs;

            return $run;
        })->values();
    }

    #[Test]
    public function it_builds_all_five_domain_plans_with_structure(): void
    {
        $plans = (new DomainPlansBuilder)->build($this->runs([
            'offer-builder' => ['offer_name' => 'باقة عبير'],
        ]));

        $this->assertEqualsCanonicalizing(['content', 'promotion', 'offer', 'journey', 'performance'], array_keys($plans));
        foreach ($plans as $plan) {
            $this->assertNotSame('', $plan['title']);
            $this->assertNotSame('', $plan['goal']);
            $this->assertNotEmpty($plan['sections']);
            foreach ($plan['sections'] as $sec) {
                $this->assertNotSame('', $sec['heading']);
                $this->assertNotEmpty($sec['items']);
            }
        }
    }

    #[Test]
    public function present_input_is_reflected_absent_input_becomes_recommendation(): void
    {
        $plans = (new DomainPlansBuilder)->build($this->runs([
            'offer-builder' => ['offer_guarantee' => 'إرجاع خلال 14 يوماً'],
            // لا سلّم قيمة → توصية
        ]));

        $offerItems = implode(' | ', array_merge(...array_map(fn ($s) => $s['items'], $plans['offer']['sections'])));
        $this->assertStringContainsString('إرجاع خلال 14 يوماً', $offerItems);   // مدخل حاضر يظهر
        $this->assertStringContainsString('مقترح', $offerItems);                  // مدخل غائب يصبح توصية
    }

    #[Test]
    public function content_plan_uses_the_known_advantage(): void
    {
        $plans = (new DomainPlansBuilder)->build($this->runs([
            'competitor-analysis' => ['own_advantage' => 'ثبات 12 ساعة بنصف السعر'],
        ]));

        $contentItems = implode(' | ', array_merge(...array_map(fn ($s) => $s['items'], $plans['content']['sections'])));
        $this->assertStringContainsString('ثبات 12 ساعة', $contentItems);
    }

    #[Test]
    public function empty_project_still_yields_actionable_recommendations(): void
    {
        $plans = (new DomainPlansBuilder)->build($this->runs([]));

        $all = '';
        foreach ($plans as $plan) {
            foreach ($plan['sections'] as $sec) {
                $all .= implode(' ', $sec['items']);
            }
        }
        $this->assertStringContainsString('مقترح', $all); // لا فراغ — توصيات ملموسة
    }
}
