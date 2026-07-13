<?php

namespace Tests\Feature\AI\Worker;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\DocumentExtractionContract;
use App\Domain\AI\Worker\KnowledgeUploadJobDispatcher;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentExtractionContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function binary_jobs_publish_the_bounded_v2_extraction_contract(): void
    {
        config()->set('services.knowledge.structured_extraction', true);
        $upload = $this->upload('application/pdf', 'report.pdf');

        $job = app(KnowledgeUploadJobDispatcher::class)->dispatch($upload);
        $contract = $job->payload_json['extraction_contract'];

        $this->assertSame('v2', $contract['version']);
        $this->assertSame(100, $contract['max_chunks']);
        $this->assertSame(350000, $contract['max_text_chars']);
        $this->assertSame([
            'page', 'image_region', 'docx_paragraph', 'docx_table',
            'xlsx_cell', 'xlsx_row', 'xlsx_table',
        ], $contract['locator_types']);
        $this->assertSame($upload->sha256, $job->payload_json['expected_sha256']);
    }

    #[Test]
    public function structured_extraction_contract_is_omitted_until_the_rollout_flag_is_enabled(): void
    {
        config()->set('services.knowledge.structured_extraction', false);
        $job = app(KnowledgeUploadJobDispatcher::class)->dispatch(
            $this->upload('application/pdf', 'legacy.pdf'),
        );

        $this->assertArrayNotHasKey('extraction_contract', $job->payload_json);
        $this->assertSame(hash('sha256', 'contract'), $job->payload_json['expected_sha256']);
    }

    #[Test]
    public function contract_rejects_unknown_locator_types_and_unbounded_payloads(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DocumentExtractionContract::validateResult([
            'contract_version' => 'v2',
            'text' => 'valid text',
            'chunks' => [[
                'content' => 'valid text',
                'locator' => ['type' => 'filesystem_path', 'path' => 'C:\\secret'],
            ]],
        ]);
    }

    private function upload(string $mime, string $name): KnowledgeUpload
    {
        $user = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $user->id, 'name' => 'Files',
            'billing_email' => $user->email, 'status' => 'active',
        ]);
        $workspace = Workspace::query()->create([
            'account_id' => $account->id, 'name' => 'Files', 'type' => 'team', 'status' => 'active',
        ]);
        $client = Client::query()->create([
            'workspace_id' => $workspace->id, 'name' => 'Files', 'status' => 'active',
        ]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id, 'client_id' => $client->id,
            'name' => 'Files', 'stage' => 1, 'status' => 'active',
        ]);

        return KnowledgeUpload::query()->create([
            'public_id' => 'upl_contract', 'account_id' => $account->id,
            'workspace_id' => $workspace->id, 'project_id' => $project->id,
            'uploaded_by_user_id' => $user->id, 'disk' => 'local',
            'path' => 'knowledge-uploads/contract.pdf', 'original_name' => $name,
            'mime_type' => $mime, 'extension' => 'pdf', 'byte_size' => 100,
            'sha256' => hash('sha256', 'contract'), 'status' => 'stored',
        ]);
    }
}
