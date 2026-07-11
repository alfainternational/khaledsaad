<?php

namespace App\Domain\AI\Kernel\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;
use UnexpectedValueException;

/**
 * متجر المعرفة الذاتية للعقل — "بناء المعرفة الخاصة + التعلم الدائم".
 *
 * معرفة ملفّية (storage/app/ai-knowledge/*.json) على نمط memdir في cloud:
 * لا قاعدة متجهات، لا جدول جديد، لا هجرة — يكتبه أمر cron ليلاً (ai:learn)
 * ويقرأه محرّكا الاستدلال والتنبؤ. يناسب الاستضافة المشتركة تماماً.
 *
 * كل ملف = حقيقة/نمط واحد تعلّمه النظام من استخدام فعلي.
 */
class KnowledgeStore
{
    private const DISK = 'local';

    private const ROOT = 'ai-knowledge';

    public function __construct(
        private readonly ?StructuredKnowledgeRepository $structured = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function remember(string $key, array $data): void
    {
        $payload = [
            'key' => $key,
            'data' => $data,
            'learned_at' => now()->toIso8601String(),
        ];
        $disk = Storage::disk(self::DISK);
        $written = $disk->put(
            $this->path($key),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
        );

        if ($written !== true) {
            Log::warning('Legacy knowledge write failed.', [
                'key_hash' => $this->keyHash($key),
                'reason' => 'storage_write_failed',
            ]);

            return;
        }

        if ($this->dualWriteEnabled()) {
            $this->withMirrorLock($disk, $key, function () use ($disk, $key): void {
                $memory = $this->legacyMemory($disk, $key);

                if ($memory === null) {
                    $this->logSkipped($key, 'legacy_payload_unreadable');

                    return;
                }

                $this->mirrorStructured($disk, $memory['key'], $memory['data']);
            });
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recall(string $key): ?array
    {
        $path = $this->path($key);
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk(self::DISK)->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $files = Storage::disk(self::DISK)->files(self::ROOT);
        $out = [];
        foreach ($files as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }
            $decoded = json_decode((string) Storage::disk(self::DISK)->get($file), true);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    public function forget(string $key): void
    {
        $disk = Storage::disk(self::DISK);
        $data = $this->legacyData($disk, $key);
        $deleted = $disk->delete($this->path($key));

        if ($deleted !== true) {
            Log::warning('Legacy knowledge delete failed.', [
                'key_hash' => $this->keyHash($key),
                'reason' => 'storage_delete_failed',
            ]);

            return;
        }

        if (! $this->dualWriteEnabled()) {
            return;
        }

        $this->withMirrorLock($disk, $key, function () use ($disk, $key, $data): void {
            $mappingPath = $this->mappingPath($key);
            $mappingExists = $disk->exists($mappingPath);
            $context = $mappingExists
                ? $this->mappedContext($disk, $key)
                : $this->contextFromLegacyData($key, $data);

            if ($context === null) {
                if ($mappingExists) {
                    $this->logSkipped($key, 'scope_mapping_invalid');
                }

                return;
            }

            try {
                $repository = $this->structured ?? app(StructuredKnowledgeRepository::class);
                $repository->deactivateDocuments($context['scope'], 'legacy_memory', $context['canonical_uri']);

                if ($mappingExists && $disk->delete($mappingPath) !== true) {
                    Log::warning('Structured knowledge scope mapping cleanup failed.', [
                        'key_hash' => $this->keyHash($key),
                        'reason' => 'mapping_delete_failed',
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Structured knowledge delete failed.', [
                    'key_hash' => $this->keyHash($key),
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    private function path(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9._-]+/i', '_', $key) ?: 'unknown';

        return self::ROOT.'/'.$safe.'.json';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mirrorStructured(FilesystemAdapter $disk, string $key, array $data): void
    {
        if (! $this->dualWriteEnabled()) {
            return;
        }

        try {
            $repository = $this->structured ?? app(StructuredKnowledgeRepository::class);
            $scope = $this->scopeFor($key, $data);

            if ($scope === null) {
                return;
            }

            $canonicalUri = $this->structuredUri($key);
            $keyHash = $this->keyHash($key);
            $content = implode("\n", $this->flatten($data));
            $previousContext = $this->mappedContextForPath($disk, $key);

            if ($previousContext !== null
                && ($previousContext['scope']->key() !== $scope->key()
                    || $previousContext['canonical_uri'] !== $canonicalUri)) {
                $repository->deactivateDocuments(
                    $previousContext['scope'],
                    'legacy_memory',
                    $previousContext['canonical_uri'],
                );
            }

            $repository->storeDocument(
                $scope,
                'legacy_memory',
                $canonicalUri,
                'Legacy memory '.substr($keyHash, 0, 12),
                $content,
                [[
                    'heading' => null,
                    'content' => $content,
                    'locator' => ['canonical_uri' => $canonicalUri, 'key_hash' => $keyHash],
                ]],
                50,
            );

            $this->writeMapping($disk, $key, $scope, $canonicalUri);
        } catch (Throwable $exception) {
            Log::warning('Structured knowledge dual write failed.', [
                'key_hash' => $this->keyHash($key),
                'exception' => $exception::class,
            ]);
        }
    }

    private function dualWriteEnabled(): bool
    {
        return (bool) config('services.knowledge.structured_store', false)
            && (bool) config('services.knowledge.dual_write', false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function scopeFor(string $key, array $data): ?KnowledgeScope
    {
        if ($this->isGlobalKey($key)) {
            if ($this->containsTenantIdentifier($data)) {
                $this->logSkipped($key, 'tenant_data_not_global');

                return null;
            }

            return KnowledgeScope::global();
        }

        if (preg_match('/\Amonitor\.performance\.ws([1-9]\d*)\z/D', $key, $matches) === 1) {
            return $this->workspaceScope($key, $data, (int) $matches[1]);
        }

        if (preg_match('/\Aagent\.[a-z0-9._-]+\.ws([1-9]\d*)\.[a-z0-9._-]+\z/D', $key, $matches) === 1) {
            $workspaceId = (int) $matches[1];
            $workspaceScope = $this->workspaceScope($key, $data, $workspaceId);

            if ($workspaceScope === null || ! array_key_exists('project_id', $data) || $data['project_id'] === null) {
                return $workspaceScope;
            }

            if (! is_int($data['project_id']) || $data['project_id'] <= 0) {
                $this->logSkipped($key, 'scope_unresolved');

                return null;
            }

            $project = Project::query()
                ->whereKey($data['project_id'])
                ->where('workspace_id', $workspaceId)
                ->first();

            if ($project === null) {
                $this->logSkipped($key, 'scope_unresolved');

                return null;
            }

            return KnowledgeScope::forProject(
                $workspaceScope->accountId,
                $workspaceScope->workspaceId,
                (int) $project->id,
            );
        }

        $this->logSkipped($key, 'scope_unresolved');

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function workspaceScope(string $key, array $data, int $workspaceId): ?KnowledgeScope
    {
        if (($data['workspace_id'] ?? null) !== $workspaceId) {
            $this->logSkipped($key, 'scope_unresolved');

            return null;
        }

        $workspace = Workspace::query()->find($workspaceId);

        if ($workspace === null || (int) $workspace->account_id <= 0) {
            $this->logSkipped($key, 'scope_unresolved');

            return null;
        }

        return KnowledgeScope::fromWorkspace($workspace);
    }

    private function isGlobalKey(string $key): bool
    {
        return $key === 'patterns.global'
            || preg_match('/\A(?:playbook|teach)\.[a-z0-9][a-z0-9._-]*\z/D', $key) === 1;
    }

    private function containsTenantIdentifier(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), [
                'account_id',
                'workspace_id',
                'project_id',
                'client_id',
                'user_id',
            ], true)) {
                return true;
            }

            if (is_array($value) && $this->containsTenantIdentifier($value)) {
                return true;
            }
        }

        return false;
    }

    private function keyHash(string $key): string
    {
        return hash('sha256', $key);
    }

    private function logSkipped(string $key, string $reason): void
    {
        Log::notice('Structured knowledge mirror skipped.', [
            'key_hash' => $this->keyHash($key),
            'reason' => $reason,
        ]);
    }

    private function structuredUri(string $key): string
    {
        return 'legacy://sha256/'.hash('sha256', $key."\0".$this->path($key));
    }

    private function mappingPath(string $key): string
    {
        return self::ROOT.'/.structured-map/'.hash('sha256', $this->path($key)).'.json';
    }

    private function writeMapping(
        FilesystemAdapter $disk,
        string $key,
        KnowledgeScope $scope,
        string $canonicalUri,
    ): void {
        $mapping = [
            'version' => 1,
            'key_hash' => $this->keyHash($key),
            'canonical_uri' => $canonicalUri,
            'visibility' => $scope->visibility,
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'project_id' => $scope->projectId,
        ];
        $mapping['integrity_hash'] = $this->mappingIntegrity($mapping);
        $encoded = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if ($disk->put($this->mappingPath($key), $encoded) !== true) {
            Log::warning('Structured knowledge scope mapping write failed.', [
                'key_hash' => $this->keyHash($key),
                'reason' => 'mapping_write_failed',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function mappingIntegrity(array $mapping): string
    {
        unset($mapping['integrity_hash']);

        return hash_hmac(
            'sha256',
            json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }

    /**
     * @return array{scope: KnowledgeScope, canonical_uri: string}|null
     */
    private function mappedContext(FilesystemAdapter $disk, string $key): ?array
    {
        $context = $this->mappedContextForPath($disk, $key);

        if ($context === null
            || $context['key_hash'] !== $this->keyHash($key)
            || $context['canonical_uri'] !== $this->structuredUri($key)) {
            return null;
        }

        return [
            'scope' => $context['scope'],
            'canonical_uri' => $context['canonical_uri'],
        ];
    }

    /**
     * @return array{scope: KnowledgeScope, canonical_uri: string, key_hash: string}|null
     */
    private function mappedContextForPath(FilesystemAdapter $disk, string $key): ?array
    {
        try {
            $mapping = json_decode($disk->get($this->mappingPath($key)), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($mapping)
            || ($mapping['version'] ?? null) !== 1
            || ! is_string($mapping['key_hash'] ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $mapping['key_hash']) !== 1
            || ! is_string($mapping['canonical_uri'] ?? null)
            || ! str_starts_with($mapping['canonical_uri'], 'legacy://sha256/')
            || ! is_string($mapping['integrity_hash'] ?? null)
            || ! hash_equals($this->mappingIntegrity($mapping), $mapping['integrity_hash'])) {
            return null;
        }

        $scope = $this->scopeFromMapping($mapping);

        return $scope === null ? null : [
            'scope' => $scope,
            'canonical_uri' => $mapping['canonical_uri'],
            'key_hash' => $mapping['key_hash'],
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function scopeFromMapping(array $mapping): ?KnowledgeScope
    {
        $accountId = $mapping['account_id'] ?? null;
        $workspaceId = $mapping['workspace_id'] ?? null;
        $projectId = $mapping['project_id'] ?? null;

        if (($mapping['visibility'] ?? null) === 'global'
            && $accountId === null && $workspaceId === null && $projectId === null) {
            return KnowledgeScope::global();
        }

        if (! is_int($accountId) || ! is_int($workspaceId) || $accountId <= 0 || $workspaceId <= 0) {
            return null;
        }

        $workspace = Workspace::query()
            ->whereKey($workspaceId)
            ->where('account_id', $accountId)
            ->first();

        if ($workspace === null) {
            return null;
        }

        if (($mapping['visibility'] ?? null) === 'workspace' && $projectId === null) {
            return KnowledgeScope::forWorkspace($accountId, $workspaceId);
        }

        if (($mapping['visibility'] ?? null) !== 'project' || ! is_int($projectId) || $projectId <= 0) {
            return null;
        }

        $projectExists = Project::query()
            ->whereKey($projectId)
            ->where('workspace_id', $workspaceId)
            ->exists();

        return $projectExists ? KnowledgeScope::forProject($accountId, $workspaceId, $projectId) : null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{scope: KnowledgeScope, canonical_uri: string}|null
     */
    private function contextFromLegacyData(string $key, ?array $data): ?array
    {
        if ($data === null) {
            if (! $this->isGlobalKey($key)) {
                return null;
            }

            return ['scope' => KnowledgeScope::global(), 'canonical_uri' => $this->structuredUri($key)];
        }

        $scope = $this->scopeFor($key, $data);

        return $scope === null ? null : ['scope' => $scope, 'canonical_uri' => $this->structuredUri($key)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function legacyData(FilesystemAdapter $disk, string $key): ?array
    {
        $path = $this->path($key);

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) && is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
    }

    /**
     * @return array{key: string, data: array<string, mixed>}|null
     */
    private function legacyMemory(FilesystemAdapter $disk, string $key): ?array
    {
        $path = $this->path($key);

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        if (! is_array($decoded)
            || ! is_string($decoded['key'] ?? null)
            || ! is_array($decoded['data'] ?? null)) {
            return null;
        }

        return ['key' => $decoded['key'], 'data' => $decoded['data']];
    }

    /**
     * @param  Closure(FilesystemAdapter): void  $operation
     */
    private function withMirrorLock(
        FilesystemAdapter $disk,
        string $key,
        Closure $operation,
    ): bool {
        try {
            $lockPath = $disk->path(self::ROOT.'/.locks/'.hash('sha256', $this->path($key)).'.lock');
            File::ensureDirectoryExists(dirname($lockPath));
            $handle = @fopen($lockPath, 'c+');
        } catch (Throwable) {
            $handle = false;
        }

        if ($handle === false) {
            Log::warning('Knowledge mirror lock unavailable.', [
                'key_hash' => $this->keyHash($key),
                'reason' => 'lock_open_failed',
            ]);

            return false;
        }

        $waitMilliseconds = max(50, min(2000, (int) config('services.knowledge.lock_wait_milliseconds', 500)));
        $deadline = microtime(true) + ($waitMilliseconds / 1000);
        $locked = false;

        do {
            $locked = flock($handle, LOCK_EX | LOCK_NB);

            if (! $locked) {
                usleep(10_000);
            }
        } while (! $locked && microtime(true) < $deadline);

        if (! $locked) {
            fclose($handle);
            Log::warning('Knowledge mirror lock timed out.', [
                'key_hash' => $this->keyHash($key),
                'reason' => 'lock_timeout',
            ]);

            return false;
        }

        try {
            $operation();

            return true;
        } catch (Throwable $exception) {
            Log::warning('Knowledge mirror operation failed.', [
                'key_hash' => $this->keyHash($key),
                'exception' => $exception::class,
            ]);

            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     *
     * @throws JsonException
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        $lines = [];

        foreach ($data as $key => $value) {
            $segment = $this->escapePathSegment((string) $key);
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                $lines = array_merge($lines, $this->flatten($value, $path));
            } elseif (is_scalar($value) || $value === null) {
                $lines[] = $path.': '.json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
                );
            } else {
                throw new UnexpectedValueException('Legacy knowledge contains an unsupported value.');
            }
        }

        return $lines;
    }

    private function escapePathSegment(string $segment): string
    {
        $segment = str_replace('~', '~0', $segment);
        $segment = str_replace('.', '~1', $segment);

        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            fn (array $match): string => sprintf('~u%04X', ord($match[0])),
            $segment,
        ) ?? $segment;
    }
}
