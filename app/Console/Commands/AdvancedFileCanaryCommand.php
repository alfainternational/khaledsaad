<?php

namespace App\Console\Commands;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeRetriever;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeEmbedding;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Worker\KnowledgeUploadJobDispatcher;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdvancedFileCanaryCommand extends Command
{
    private const ACCOUNT_NAME = '__AI_ADVANCED_FILE_CANARY__';

    private const FILES = [
        'image.png' => ['mime' => 'image/png', 'term' => 'CANARYIMAGE', 'locator' => 'image_region'],
        'text.pdf' => ['mime' => 'application/pdf', 'term' => 'CANARYTEXTPDF84', 'locator' => 'page'],
        'scan.pdf' => ['mime' => 'application/pdf', 'term' => 'CANARYSCAN', 'locator' => 'image_region'],
        'table.docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'term' => 'CANARYDOCX31', 'locator' => 'docx_table'],
        'formula.xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'term' => 'CANARYXLSXQ4', 'locator' => 'xlsx_row'],
    ];

    private const API_FILE = [
        'mime' => 'application/pdf', 'term' => 'CANARYTEXTPDF84', 'locator' => 'page',
    ];

    protected $signature = 'knowledge:file-canary
        {action : enqueue, setup-api, status, or cleanup}
        {--directory= : Absolute directory containing the five canary files}
        {--owner-user-id= : Existing user ID that owns the isolated canary account}
        {--json : Emit machine-readable output}';

    protected $description = 'Run and clean an isolated production canary for advanced local file extraction';

    public function handle(KnowledgeUploadJobDispatcher $dispatcher, KnowledgeRetriever $retriever): int
    {
        return match ((string) $this->argument('action')) {
            'enqueue' => $this->enqueue($dispatcher),
            'setup-api' => $this->setupApi(),
            'status' => $this->status($retriever),
            'cleanup' => $this->cleanup(),
            default => $this->invalidAction(),
        };
    }

    private function enqueue(KnowledgeUploadJobDispatcher $dispatcher): int
    {
        if (Account::query()->where('name', self::ACCOUNT_NAME)->exists()) {
            return $this->result(['ok' => false, 'error' => 'canary_already_exists'], self::FAILURE);
        }
        $directory = rtrim((string) $this->option('directory'), '\\/');
        $owner = User::query()->find((int) $this->option('owner-user-id'));
        if (! $owner || ! is_dir($directory)) {
            return $this->result(['ok' => false, 'error' => 'owner_or_directory_invalid'], self::INVALID);
        }
        foreach (array_keys(self::FILES) as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            if (! is_file($path) || filesize($path) < 1 || filesize($path) > 8 * 1024 * 1024) {
                return $this->result(['ok' => false, 'error' => 'fixture_invalid', 'file' => $name], self::INVALID);
            }
        }

        $originalFlag = config('services.knowledge.structured_extraction');
        try {
            [$account, , $project] = $this->createTopology($owner);
            config()->set('services.knowledge.structured_extraction', true);
            foreach (self::FILES as $name => $definition) {
                $source = $directory.DIRECTORY_SEPARATOR.$name;
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                $sha256 = hash_file('sha256', $source);
                $path = 'knowledge-uploads/canary/'.$account->id.'/'.$sha256.'.'.$extension;
                Storage::disk('local')->put($path, file_get_contents($source));
                $upload = KnowledgeUpload::query()->create([
                    'public_id' => 'upl_'.Str::lower((string) Str::ulid()),
                    'account_id' => $account->id,
                    'workspace_id' => $project->workspace_id,
                    'project_id' => $project->id,
                    'uploaded_by_user_id' => $owner->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $name,
                    'mime_type' => $definition['mime'],
                    'extension' => $extension,
                    'byte_size' => filesize($source),
                    'sha256' => $sha256,
                    'status' => 'stored',
                ]);
                $dispatcher->dispatch($upload);
            }

            return $this->result([
                'ok' => true,
                'account_id' => $account->id,
                'project_id' => $project->id,
                'queued' => count(self::FILES),
            ]);
        } catch (\Throwable $exception) {
            $this->removeCanary();
            throw $exception;
        } finally {
            config()->set('services.knowledge.structured_extraction', $originalFlag);
        }
    }

    private function setupApi(): int
    {
        if (Account::query()->where('name', self::ACCOUNT_NAME)->exists()) {
            return $this->result(['ok' => false, 'error' => 'canary_already_exists'], self::FAILURE);
        }
        $owner = User::query()->find((int) $this->option('owner-user-id'));
        if (! $owner) {
            return $this->result(['ok' => false, 'error' => 'owner_invalid'], self::INVALID);
        }
        [$account, $workspace, $project] = $this->createTopology($owner);
        WorkspaceMember::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
        $token = $owner->createToken('advanced-file-canary')->plainTextToken;

        return $this->result([
            'ok' => true,
            'account_id' => $account->id,
            'workspace_public_id' => $workspace->public_id,
            'project_public_id' => $project->public_id,
            'api_token' => $token,
        ]);
    }

    private function status(KnowledgeRetriever $retriever): int
    {
        $account = Account::query()->where('name', self::ACCOUNT_NAME)->first();
        if (! $account) {
            return $this->result(['ok' => false, 'error' => 'canary_missing'], self::FAILURE);
        }
        $projects = Project::query()->whereHas('workspace', fn ($query) => $query->where('account_id', $account->id))->get();
        $project = $projects->firstWhere('name', 'Advanced File Canary');
        $control = $projects->firstWhere('name', 'Canary Isolation Control');
        if (! $project || ! $control) {
            throw new RuntimeException('The canary project topology is incomplete.');
        }
        $uploads = KnowledgeUpload::query()
            ->where('account_id', $account->id)
            ->with('source.documents.chunks')
            ->get()
            ->keyBy('original_name');
        $checks = [];
        foreach ($uploads as $name => $upload) {
            $definition = self::FILES[$name] ?? ($name === 'chunk-canary.pdf' ? self::API_FILE : null);
            if ($definition === null) {
                $checks[$name] = ['status' => $upload->status, 'passed' => false, 'error' => 'unexpected_canary_file'];

                continue;
            }
            $document = $upload?->source?->documents->firstWhere('status', 'active');
            $locatorTypes = $document?->chunks->pluck('locator_json.type')->filter()->unique()->values()->all() ?? [];
            $scope = KnowledgeScope::forProject($account->id, $project->workspace_id, $project->id);
            $controlScope = KnowledgeScope::forProject($account->id, $control->workspace_id, $control->id);
            $sourceUri = 'upload://'.($upload?->public_id ?? 'missing');
            $evidence = $retriever->retrieve($scope, $definition['term'], 10)
                ->first(fn ($item) => $item->sourceUri === $sourceUri);
            $leaked = $retriever->retrieve($controlScope, $definition['term'], 10)
                ->contains(fn ($item) => $item->sourceUri === $sourceUri);
            $checks[$name] = [
                'status' => $upload?->status,
                'contract' => $upload?->extraction_meta_json['contract_version'] ?? null,
                'locator_types' => $locatorTypes,
                'citation' => $evidence?->citation,
                'isolated' => ! $leaked,
                'passed' => $upload?->status === 'indexed'
                    && ($upload?->extraction_meta_json['contract_version'] ?? null) === 'v2'
                    && in_array($definition['locator'], $locatorTypes, true)
                    && $evidence !== null
                    && ! $leaked,
            ];
        }
        $chunkIds = $uploads->flatMap(fn (KnowledgeUpload $upload) => $upload->source?->documents
            ->where('status', 'active')->flatMap->chunks->pluck('id') ?? collect())->unique();
        $embedded = KnowledgeEmbedding::query()->whereIn('knowledge_chunk_id', $chunkIds)->where('status', 'active')->distinct()->count('knowledge_chunk_id');
        $allPassed = $checks !== [] && collect($checks)->every('passed')
            && $chunkIds->count() > 0 && $embedded === $chunkIds->count();

        return $this->result([
            'ok' => $allPassed,
            'files' => $checks,
            'chunks' => $chunkIds->count(),
            'embedded_chunks' => $embedded,
            'embedding_coverage_percent' => $chunkIds->count() > 0 ? round(($embedded / $chunkIds->count()) * 100, 2) : 0,
        ], $allPassed ? self::SUCCESS : self::FAILURE);
    }

    private function cleanup(): int
    {
        $removed = $this->removeCanary();

        return $this->result(['ok' => true, 'removed' => $removed]);
    }

    private function removeCanary(): bool
    {
        $account = Account::query()->where('name', self::ACCOUNT_NAME)->first();
        if (! $account) {
            return false;
        }
        KnowledgeUpload::query()->where('account_id', $account->id)->get()->each(
            fn (KnowledgeUpload $upload) => Storage::disk($upload->disk)->delete($upload->path),
        );
        $account->owner?->tokens()->where('name', 'advanced-file-canary')->delete();
        Storage::disk('local')->deleteDirectory('knowledge-uploads/canary');
        $workspaceIds = Workspace::withTrashed()->where('account_id', $account->id)->pluck('id');
        DB::table('workspace_data')->whereIn('workspace_id', $workspaceIds)->delete();
        $account->forceDelete();

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function result(array $payload, int $code = self::SUCCESS): int
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->line($this->option('json') ? $encoded : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $code;
    }

    private function invalidAction(): int
    {
        return $this->result(['ok' => false, 'error' => 'invalid_action'], self::INVALID);
    }

    /** @return array{Account, Workspace, Project} */
    private function createTopology(User $owner): array
    {
        return DB::transaction(function () use ($owner): array {
            $account = Account::query()->create([
                'owner_user_id' => $owner->id,
                'name' => self::ACCOUNT_NAME,
                'billing_email' => $owner->email,
                'status' => 'active',
            ]);
            $workspace = Workspace::query()->create([
                'account_id' => $account->id,
                'name' => self::ACCOUNT_NAME,
                'type' => 'personal',
                'status' => 'active',
            ]);
            $client = Client::query()->create([
                'workspace_id' => $workspace->id,
                'name' => self::ACCOUNT_NAME,
                'status' => 'active',
            ]);
            $project = Project::query()->create([
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'name' => 'Advanced File Canary',
                'stage' => 1,
                'status' => 'active',
            ]);
            Project::query()->create([
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'name' => 'Canary Isolation Control',
                'stage' => 1,
                'status' => 'active',
            ]);

            return [$account, $workspace, $project];
        });
    }
}
