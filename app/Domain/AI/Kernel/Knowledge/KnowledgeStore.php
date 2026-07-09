<?php

namespace App\Domain\AI\Kernel\Knowledge;

use Illuminate\Support\Facades\Storage;

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
}
