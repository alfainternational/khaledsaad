<?php

namespace App\Domain\AI\Kernel\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\LegacyKnowledgeIdentityResolver;
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
        private readonly ?LegacyKnowledgeIdentityResolver $identityResolver = null,
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
            $identity = $this->resolver()->resolve($key, $data);
            $scope = $identity['scope'];

            if ($scope === null) {
                $this->logSkipped($key, $identity['failure_reason'] ?? 'scope_unresolved');

                return;
            }

            $canonicalUri = $identity['canonical_uri'];
            $keyHash = $identity['key_hash'];
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

            try {
                $mappingWritten = $this->writeMapping($disk, $key, $scope, $canonicalUri);
            } catch (Throwable) {
                $mappingWritten = false;
            }

            $verifiedContext = $mappingWritten ? $this->mappedContext($disk, $key) : null;

            if ($verifiedContext === null || $verifiedContext['scope']->key() !== $scope->key()) {
                Log::warning('Structured knowledge scope mapping write failed.', [
                    'key_hash' => $keyHash,
                    'reason' => 'mapping_write_failed',
                ]);

                return;
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

    private function resolver(): LegacyKnowledgeIdentityResolver
    {
        return $this->identityResolver ?? app(LegacyKnowledgeIdentityResolver::class);
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
        return 'legacy://sha256/'.$this->keyHash($key);
    }

    private function mappingPath(string $key): string
    {
        return self::ROOT.'/.structured-map/'.hash('sha256', $this->path($key)).'.json';
    }

    protected function writeMapping(
        FilesystemAdapter $disk,
        string $key,
        KnowledgeScope $scope,
        string $canonicalUri,
    ): bool {
        $mapping = [
            'version' => 2,
            'key_hash' => $this->keyHash($key),
            'path_hash' => hash('sha256', $this->path($key)),
            'canonical_uri' => $canonicalUri,
            'visibility' => $scope->visibility,
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'project_id' => $scope->projectId,
        ];
        $signingKey = (string) config('app.key');
        $mapping['signing_key_id'] = hash('sha256', $signingKey);
        $mapping['signature'] = $this->mappingSignature($mapping, $signingKey);
        $encoded = json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $disk->put($this->mappingPath($key), $encoded) === true;
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
            || array_keys($mapping) !== [
                'version',
                'key_hash',
                'path_hash',
                'canonical_uri',
                'visibility',
                'account_id',
                'workspace_id',
                'project_id',
                'signing_key_id',
                'signature',
            ]
            || ($mapping['version'] ?? null) !== 2
            || ! is_string($mapping['key_hash'] ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $mapping['key_hash']) !== 1
            || ($mapping['path_hash'] ?? null) !== hash('sha256', $this->path($key))
            || ! is_string($mapping['canonical_uri'] ?? null)
            || $mapping['canonical_uri'] !== 'legacy://sha256/'.$mapping['key_hash']
            || ! $this->hasValidMappingSignature($mapping)) {
            return null;
        }

        $scope = $this->scopeFromMapping($mapping);

        return $scope === null ? null : [
            'scope' => $scope,
            'canonical_uri' => $mapping['canonical_uri'],
            'key_hash' => $mapping['key_hash'],
        ];
    }

    /** @param array<string, mixed> $mapping */
    private function hasValidMappingSignature(array $mapping): bool
    {
        if (! is_string($mapping['signing_key_id'] ?? null)
            || ! is_string($mapping['signature'] ?? null)) {
            return false;
        }

        $keys = array_merge(
            [(string) config('app.key')],
            array_filter(
                (array) config('services.knowledge.mapping_previous_keys', []),
                fn (mixed $key): bool => is_string($key) && $key !== '',
            ),
        );

        foreach (array_unique($keys) as $key) {
            if (hash_equals(hash('sha256', $key), $mapping['signing_key_id'])
                && hash_equals($this->mappingSignature($mapping, $key), $mapping['signature'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $mapping */
    private function mappingSignature(array $mapping, string $key): string
    {
        unset($mapping['signature']);

        return hash_hmac(
            'sha256',
            json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $key,
        );
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
            $identity = $this->resolver()->resolve($key, []);

            return $identity['scope'] === null ? null : [
                'scope' => $identity['scope'],
                'canonical_uri' => $identity['canonical_uri'],
            ];
        }

        $identity = $this->resolver()->resolve($key, $data);

        return $identity['scope'] === null ? null : [
            'scope' => $identity['scope'],
            'canonical_uri' => $identity['canonical_uri'],
        ];
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
