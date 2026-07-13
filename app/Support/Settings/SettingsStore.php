<?php

namespace App\Support\Settings;

use Illuminate\Support\Facades\Storage;

/**
 * مخزن إعدادات ملفّي قابل للتبديل من الآدمن (storage/app/settings.json).
 *
 * يحقّق الدستور §32: ما يُفتح/يُغلق لا يُكتب في الكود بل يُقرأ من الإعدادات.
 * بلا migration ولا جدول — يعمل على الاستضافة المشتركة. القيم تُطبَّق فوق
 * config() عند الإقلاع، فتلتقطها كل المستهلكات تلقائياً دون إعادة ربط.
 */
class SettingsStore
{
    private const DISK = 'local';

    private const PATH = 'settings.json';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (! Storage::disk(self::DISK)->exists(self::PATH)) {
            return $this->cache = [];
        }

        $decoded = json_decode((string) Storage::disk(self::DISK)->get(self::PATH), true);

        return $this->cache = is_array($decoded) ? $decoded : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function getFresh(string $key, mixed $default = null): mixed
    {
        $this->cache = null;

        return $this->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $data = $this->all();
        $data[$key] = $value;
        $this->persist($data);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        $this->persist(array_merge($this->all(), $values));
    }

    public function forget(string $key): void
    {
        $data = $this->all();
        unset($data[$key]);
        $this->persist($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data): void
    {
        Storage::disk(self::DISK)->put(
            self::PATH,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}',
        );
        $this->cache = $data;
    }
}
