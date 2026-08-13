<?php

namespace Tests\Feature;

use App\Models\AgencyReport;
use App\Models\Finding;
use App\Models\HumanTrace;
use App\Models\Objective;
use App\Models\Recommendation;
use App\Models\RecommendationTemplate;
use App\Models\Report;
use App\Models\ReportRevision;
use App\Models\ScoringItem;
use App\Models\TemplateGap;
use App\Models\ValidationFinding;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedReportContractMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unified_report_contract_schema_is_available_without_replacing_legacy_entities(): void
    {
        foreach ([
            'objectives', 'recommendation_templates', 'template_bindings',
            'report_revisions', 'validation_findings', 'human_traces',
            'template_gaps', 'scoring_items',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }

        $this->assertTrue(Schema::hasColumns('reports', [
            'provenance', 'score_raw', 'score_max', 'issued_at', 'authored_at',
            'authored_by', 'validation_status', 'schema_version', 'contract_payload',
        ]));
        $this->assertTrue(Schema::hasColumns('findings', [
            'evidence_answer_id', 'evidence_quote',
        ]));
        $this->assertTrue(Schema::hasColumns('recommendations', [
            'objective_id', 'metric_objective_id', 'deliverable', 'done_when',
            'first_five_minutes', 'expected_failure', 'duration_days', 'template_id',
            'template_payload', 'degraded', 'degrade_reason', 'fallback_coaching',
        ]));
        $this->assertTrue(Schema::hasColumns('agency_reports', [
            'provenance', 'validation_status', 'schema_version',
        ]));
    }

    #[Test]
    public function contract_models_expose_their_report_relationships_and_casts(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Report)->validationFindings());
        $this->assertInstanceOf(HasMany::class, (new Report)->revisions());
        $this->assertInstanceOf(HasMany::class, (new Report)->humanTraces());
        $this->assertInstanceOf(HasMany::class, (new Report)->scoringItems());
        $this->assertInstanceOf(BelongsTo::class, (new Finding)->evidenceAnswer());
        $this->assertInstanceOf(BelongsTo::class, (new Recommendation)->objective());
        $this->assertInstanceOf(BelongsTo::class, (new Recommendation)->template());
        $this->assertInstanceOf(HasMany::class, (new Objective)->templates());

        $this->assertSame('array', (new ReportRevision)->getCasts()['diff']);
        $this->assertSame('array', (new ValidationFinding)->getCasts()['meta']);
        $this->assertSame('array', (new RecommendationTemplate)->getCasts()['body']);
        $this->assertSame('array', (new Recommendation)->getCasts()['template_payload']);
        $this->assertSame('array', (new HumanTrace)->getCasts()['meta']);
        $this->assertSame('array', (new ScoringItem)->getCasts()['answer_value']);
        $this->assertSame('datetime', (new TemplateGap)->getCasts()['last_seen_at']);
        $this->assertSame('integer', (new AgencyReport)->getCasts()['schema_version']);
    }
}
