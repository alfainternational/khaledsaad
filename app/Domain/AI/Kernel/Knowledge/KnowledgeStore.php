<?php

namespace App\Domain\AI\Kernel\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
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

        Storage::disk(self::DISK)->put(
            $this->path($key),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
        );

        $this->mirrorStructured($key, $data);
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
        Storage::disk(self::DISK)->delete($this->path($key));
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
        if (! (bool) config('services.knowledge.structured_store', false)
            || ! (bool) config('services.knowledge.dual_write', false)) {
            return;
        }

        try {
            $repository = $this->structured ?? app(StructuredKnowledgeRepository::class);
            $canonicalKey = trim($key);
            $canonicalUri = 'legacy://'.$canonicalKey;
            $content = implode("\n", $this->flatten($data));

            $repository->storeDocument(
                KnowledgeScope::global(),
                'legacy_memory',
                $canonicalUri,
                $canonicalKey,
                $content,
                [[
                    'heading' => $canonicalKey,
                    'content' => $content,
                    'locator' => ['canonical_uri' => $canonicalUri],
                ]],
                50,
            );
        } catch (Throwable $exception) {
            Log::warning('Structured knowledge dual write failed.', [
                'key' => $key,
                'exception' => $exception::class,
            ]);
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
