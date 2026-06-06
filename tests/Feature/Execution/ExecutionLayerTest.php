<?php

namespace Tests\Feature\Execution;

use App\Application\Execution\BuildExecutionPackageAction;
use App\Application\Execution\GenerateRecommendationsFromAuditAction;
use App\Domain\Account\Models\Account;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\Recommendation;
use App\Domain\Intelligence\Models\AuditFinding;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutionLayerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function audit_findings_become_prioritised_evidence_backed_recommendations(): void
    {
        [$project, $auditRun, $actor] = $this->auditWithFindings();

        $recommendations = app(GenerateRecommendationsFromAuditAction::class)
            ->handle($project, $auditRun, $actor);

        $this->assertCount(2, $recommendations);

        // Highest score_impact finding ranks first (priority 10).
        $top = $recommendations->first();
        $this->assertSame('الموقع غير آمن (HTTP)', $top->title);
        $this->assertSame(10, $top->priority);
        $this->assertSame('high', $top->estimated_impact);
        $this->assertNotEmpty($top->evidence);
        $this->assertSame('proposed', $top->status);

        // Idempotent: re-running does not duplicate.
        app(GenerateRecommendationsFromAuditAction::class)->handle($project, $auditRun, $actor);
        $this->assertSame(2, Recommendation::query()->count());
    }

    #[Test]
    public function a_recommendation_becomes_an_execution_package_with_tasks_and_asset(): void
    {
        [$project, $auditRun, $actor] = $this->auditWithFindings();
        $recommendation = app(GenerateRecommendationsFromAuditAction::class)
            ->handle($project, $auditRun, $actor)
            ->first();

        $package = app(BuildExecutionPackageAction::class)->handle($recommendation, $actor);

        $this->assertInstanceOf(ExecutionPackage::class, $package);
        $this->assertSame('proposed', $package->status);
        $this->assertSame($recommendation->id, $package->recommendation_id);
        $this->assertNotEmpty($package->measurement_plan);
        $this->assertCount(4, $package->tasks);
        $this->assertCount(1, $package->assets);
        $this->assertSame('pending', $package->tasks->first()->status);

        // The recommendation is marked accepted once packaged.
        $this->assertSame('accepted', $recommendation->fresh()->status);
    }

    /**
     * @return array{0: Project, 1: AuditRun, 2: User}
     */
    private function auditWithFindings(): array
    {
        $actor = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $actor->id,
            'name' => 'Exec Account',
            'billing_email' => $actor->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Exec Workspace',
            'type' => 'personal',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'name' => 'Exec Project',
            'stage' => 1,
            'status' => 'active',
            'sector' => 'general',
            'primary_domain' => 'https://example.com',
        ]);

        $auditRun = AuditRun::query()->create([
            'public_id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
        ]);

        // Two trusted findings + one low-confidence (must be filtered out).
        AuditFinding::query()->create([
            'audit_run_id' => $auditRun->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'website',
            'subcategory' => 'availability',
            'severity' => 'high',
            'confidence' => 0.95,
            'score_impact' => 24,
            'title' => 'الموقع غير آمن (HTTP)',
            'evidence' => 'الصفحة تُقدَّم عبر HTTP بدون شهادة SSL.',
            'recommendation' => 'فعّل HTTPS عبر شهادة SSL صالحة.',
        ]);

        AuditFinding::query()->create([
            'audit_run_id' => $auditRun->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'conversion',
            'subcategory' => 'cta',
            'severity' => 'medium',
            'confidence' => 0.8,
            'score_impact' => 12,
            'title' => 'لا يوجد زر إجراء واضح',
            'evidence' => 'الصفحة الرئيسية بلا CTA بارز.',
            'recommendation' => 'أضف زر إجراء رئيسي واضح أعلى الصفحة.',
        ]);

        AuditFinding::query()->create([
            'audit_run_id' => $auditRun->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => 'seo',
            'subcategory' => 'meta',
            'severity' => 'low',
            'confidence' => 0.3, // below threshold — excluded
            'score_impact' => 6,
            'title' => 'وصف ميتا قصير',
            'evidence' => 'meta description قصير.',
            'recommendation' => 'وسّع وصف الميتا.',
        ]);

        return [$project, $auditRun, $actor];
    }
}
