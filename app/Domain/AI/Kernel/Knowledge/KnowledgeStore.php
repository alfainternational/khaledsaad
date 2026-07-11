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
use InvalidArgumentException;
use JsonException;
use RuntimeException;
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
        $canonicalKey = $this->canonicalKey($key);

        $this->withIdentityLock($canonicalKey, function (FilesystemAdapter $disk) use ($canonicalKey, $data): void {
            $payload = [
                'key' => $canonicalKey,
                'data' => $data,
                'learned_at' => now()->toIso8601String(),
            ];

            $written = $disk->put(
                $this->path($canonicalKey),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
            );

            if ($written !== true) {
                Log::warning('Legacy knowledge write failed.', [
                    'key_hash' => $this->keyHash($canonicalKey),
                    'reason' => 'storage_write_failed',
                ]);

                return;
            }

            $this->mirrorStructured($canonicalKey, $data);
        });
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
        try {
            $canonicalKey = $this->canonicalKey($key);
            $lockIdentity = $canonicalKey;
        } catch (InvalidArgumentException) {
            $canonicalKey = null;
            $lockIdentity = 'legacy-path:'.$this->path($key);
        }

        $this->withIdentityLock($lockIdentity, function (FilesystemAdapter $disk) use ($key, $canonicalKey): void {
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

            if ($canonicalKey === null) {
                $this->logSkipped($key, 'unsafe_legacy_key');

                return;
            }

            $scope = $this->scopeFor($canonicalKey, $data ?? []);

            if ($scope === null) {
                return;
            }

            try {
                $repository = $this->structured ?? app(StructuredKnowledgeRepository::class);
                $repository->deactivateDocuments(
                    $scope,
                    'legacy_memory',
                    'legacy://'.$canonicalKey,
                );
            } catch (Throwable $exception) {
                Log::warning('Structured knowledge delete failed.', [
                    'key_hash' => $this->keyHash($canonicalKey),
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
    private function mirrorStructured(string $key, array $data): void
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

            $canonicalUri = 'legacy://'.$key;
            $content = implode("\n", $this->flatten($data));

            $repository->storeDocument(
                $scope,
                'legacy_memory',
                $canonicalUri,
                $key,
                $content,
                [[
                    'heading' => $key,
                    'content' => $content,
                    'locator' => ['canonical_uri' => $canonicalUri],
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

    private function canonicalKey(string $key): string
    {
        if (preg_match('/\A[a-z0-9](?:[a-z0-9._-]{0,198}[a-z0-9])?\z/D', $key) !== 1) {
            throw new InvalidArgumentException('Knowledge key must use a canonical lowercase ASCII identity.');
        }

        return $key;
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
     * @param  Closure(FilesystemAdapter): void  $operation
     */
    private function withIdentityLock(string $identity, Closure $operation): void
    {
        $disk = Storage::disk(self::DISK);
        $lockPath = $disk->path(self::ROOT.'/.locks/'.$this->keyHash($identity).'.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $handle = fopen($lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the knowledge identity lock.');
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
            Log::warning('Knowledge identity lock timed out.', [
                'key_hash' => $this->keyHash($identity),
                'reason' => 'lock_timeout',
            ]);

            throw new RuntimeException('Knowledge identity lock timed out.');
        }

        try {
            $operation($disk);
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
