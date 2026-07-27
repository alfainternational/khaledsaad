<?php

namespace Tests\Feature;

use App\Models\ProjectAnswer;
use App\Models\User;
use App\Services\Consultations\ConsultationEvidenceService;
use App\Services\Consultations\ConsultationService;
use App\Services\Projects\ProjectService;
use App\Services\Tools\FullDiagnosisRunner;
use App\Services\Tools\ProjectSnapshotBuilder;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationEvidenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function consultation_evidence_is_extracted_hashed_and_included_in_every_linked_tool_snapshot(): void
    {
        Storage::fake('local');
        Bus::fake();
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الأدلة']);
        ProjectAnswer::create([
            'project_id' => $project->id,
            'field_key' => 'source_consent',
            'value_json' => ['value' => 'نعم'],
            'source_tool_key' => 'consultation',
        ]);
        $session = app(ConsultationService::class)->start($project, $user);

        $evidence = app(ConsultationEvidenceService::class)->store(
            $session,
            UploadedFile::fake()->createWithContent(
                'sales-proof.txt',
                'بلغت المبيعات المؤكدة مئة طلب خلال آخر ثلاثين يومًا.',
            ),
        );

        $this->assertSame('completed', $evidence->extraction_status);
        $this->assertStringContainsString('مئة طلب', $evidence->extracted_text);
        $this->assertSame(64, strlen((string) $evidence->sha256));

        app(FullDiagnosisRunner::class)->run(
            $project,
            $user,
            FullDiagnosisRunner::MODE_AUTO,
            $session->id,
        );

        $runs = $project->runs()->get();
        $this->assertNotEmpty($runs);
        $this->assertSame([$session->id], $runs->pluck('consultation_session_id')->unique()->values()->all());

        foreach ($runs as $run) {
            $snapshot = app(ProjectSnapshotBuilder::class)->build($run->load(['project', 'toolVersion', 'answers', 'files']));
            $this->assertSame($session->uuid, data_get($snapshot, 'consultation.uuid'));
            $this->assertStringContainsString(
                'مئة طلب',
                (string) data_get($snapshot, 'consultation.evidence.0.text'),
            );
            $this->assertSame($evidence->sha256, data_get($snapshot, 'consultation.evidence.0.sha256'));
        }
    }
}
