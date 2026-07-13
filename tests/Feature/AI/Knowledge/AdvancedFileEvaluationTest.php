<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeRetriever;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\DocumentExtractionContract;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\WorkerResultApplier;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdvancedFileEvaluationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_advanced_format_keeps_citable_locators_and_remains_project_isolated(): void
    {
        Storage::fake('local');
        config()->set('services.private_worker.enabled', true);
        config()->set('services.knowledge.hybrid_retrieval', false);
        [$account, $workspace, $project, $otherProject, $user] = $this->tenant();
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'wrk_file_evaluation',
            'name' => 'File Evaluation Worker',
            'secret_ciphertext' => 'secret',
            'capabilities_json' => ['ocr', 'document_extract'],
            'status' => 'active',
            'last_seen_at' => now(),
        ]);
        $cases = [
            'image' => [
                'mime' => 'image/png', 'extension' => 'png', 'type' => 'ocr',
                'term' => 'صافيالرضا92', 'text' => 'Arabic satisfaction صافيالرضا92 and English retention 88',
                'metadata' => ['format' => 'image', 'regions' => 1, 'mean_confidence' => 94.2],
                'locator' => ['type' => 'image_region', 'region' => 1, 'bbox' => [0.1, 0.2, 0.9, 0.5], 'confidence' => 94.2],
            ],
            'text_pdf' => [
                'mime' => 'application/pdf', 'extension' => 'pdf', 'type' => 'document_extract',
                'term' => 'textpdfretention84', 'text' => 'Text PDF evidence textpdfretention84',
                'metadata' => ['format' => 'pdf', 'pages' => 2, 'ocr_pages' => 0],
                'locator' => ['type' => 'page', 'page' => 2, 'method' => 'text'],
            ],
            'scanned_pdf' => [
                'mime' => 'application/pdf', 'extension' => 'pdf', 'type' => 'document_extract',
                'term' => 'scanpdfarabic77', 'text' => 'Scanned Arabic PDF scanpdfarabic77',
                'metadata' => ['format' => 'pdf', 'pages' => 1, 'ocr_pages' => 1, 'mean_confidence' => 89.0],
                'locator' => ['type' => 'image_region', 'page' => 1, 'region' => 1, 'bbox' => [0.05, 0.1, 0.95, 0.4], 'confidence' => 89.0],
            ],
            'docx' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'extension' => 'docx', 'type' => 'document_extract',
                'term' => 'docxtablemargin31', 'text' => 'DOCX table margin docxtablemargin31',
                'metadata' => ['format' => 'docx', 'paragraphs' => 2, 'tables' => 1],
                'locator' => ['type' => 'docx_table', 'table' => 1, 'row' => 2],
            ],
            'xlsx' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => 'xlsx', 'type' => 'document_extract',
                'term' => 'xlsxformulaq4', 'text' => 'Sales formula xlsxformulaq4 =SUM(B2:B4)',
                'metadata' => ['format' => 'xlsx', 'sheets' => 1, 'tables' => 1],
                'locator' => ['type' => 'xlsx_row', 'sheet' => 'Sales', 'row' => 4, 'cells' => ['B4'], 'merged_ranges' => []],
            ],
        ];

        foreach ($cases as $name => $case) {
            [$upload, $job] = $this->job($account, $workspace, $project, $user, $name, $case);
            app(WorkerResultApplier::class)->apply($job, $worker, [
                'contract_version' => DocumentExtractionContract::VERSION,
                'input_sha256' => $upload->sha256,
                'text' => $case['text'],
                'language' => $name === 'image' || $name === 'scanned_pdf' ? 'ara+eng' : 'eng',
                'metadata' => $case['metadata'],
                'chunks' => [[
                    'heading' => strtoupper($name),
                    'content' => $case['text'],
                    'locator' => $case['locator'],
                ]],
            ]);

            $evidence = app(KnowledgeRetriever::class)->retrieve(
                KnowledgeScope::forProject($account->id, $workspace->id, $project->id),
                $case['term'],
                3,
            );
            $this->assertCount(1, $evidence, $name.' should be retrievable');
            $this->assertMatchesRegularExpression('/\A\[KB:\d+:\d+:\d+\]\z/', $evidence->first()->citation);
            $this->assertSame($case['locator']['type'], $evidence->first()->locator['type']);
            $this->assertSame('indexed', $upload->fresh()->status);

            $leaked = app(KnowledgeRetriever::class)->retrieve(
                KnowledgeScope::forProject($account->id, $workspace->id, $otherProject->id),
                $case['term'],
                3,
            );
            $this->assertCount(0, $leaked, $name.' must not leak to another project');
        }

        $this->assertGreaterThan(0, IntelligenceJob::query()->where('type', 'embeddings')->where('status', 'queued')->count());
    }

    /** @param array<string, mixed> $case
     * @return array{KnowledgeUpload, IntelligenceJob}
     */
    private function job(Account $account, Workspace $workspace, Project $project, User $user, string $name, array $case): array
    {
        $content = 'fixture-'.$name;
        $path = 'knowledge-uploads/evaluation/'.$name.'.'.$case['extension'];
        Storage::disk('local')->put($path, $content);
        $upload = KnowledgeUpload::query()->create([
            'public_id' => 'upl_eval_'.$name,
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name.'.'.$case['extension'],
            'mime_type' => $case['mime'],
            'extension' => $case['extension'],
            'byte_size' => strlen($content),
            'sha256' => hash('sha256', $content),
            'status' => 'needs_worker',
        ]);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'type' => $case['type'],
            'status' => 'leased',
            'payload_json' => [
                'upload_public_id' => $upload->public_id,
                'expected_sha256' => $upload->sha256,
                'extraction_contract' => DocumentExtractionContract::definition(),
            ],
            'input_hash' => $upload->sha256,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        return [$upload, $job];
    }

    /** @return array{Account, Workspace, Project, Project, User} */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Advanced File Evaluation',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Advanced File Evaluation',
            'type' => 'team',
            'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Evaluation',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'File Canary',
            'stage' => 1,
            'status' => 'active',
        ]);
        $otherProject = Project::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'name' => 'Isolation Control',
            'stage' => 1,
            'status' => 'active',
        ]);

        return [$account, $workspace, $project, $otherProject, $user];
    }
}
