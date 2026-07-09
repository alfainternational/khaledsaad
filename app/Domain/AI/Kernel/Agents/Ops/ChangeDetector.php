<?php

namespace App\Domain\AI\Kernel\Agents\Ops;

/**
 * كاشف التغيّر — نواة وكيل competitor-intelligence محلياً.
 *
 * يقارن لقطتين (baseline ثم أحدث) ويعيد التغيّرات المصنّفة (أُضيف/أُزيل/تغيّر).
 * أساس المراقبة المستمرة والإنذار المبكر. نقي بلا مورد خارجي؛ يُشغَّل عبر cron
 * على خطوط أساس مخزّنة. (قدرة hidden — flag intelligence.monitoring.)
 */
class ChangeDetector
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{changed: bool, count: int, changes: array<int, array{key: string, type: string, from: mixed, to: mixed}>}
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $newValue) {
            if (! array_key_exists($key, $before)) {
                $changes[] = ['key' => (string) $key, 'type' => 'added', 'from' => null, 'to' => $newValue];
            } elseif ($this->normalize($before[$key]) !== $this->normalize($newValue)) {
                $changes[] = ['key' => (string) $key, 'type' => 'changed', 'from' => $before[$key], 'to' => $newValue];
            }
        }

        foreach ($before as $key => $oldValue) {
            if (! array_key_exists($key, $after)) {
                $changes[] = ['key' => (string) $key, 'type' => 'removed', 'from' => $oldValue, 'to' => null];
            }
        }

        return [
            'changed' => $changes !== [],
            'count' => count($changes),
            'changes' => $changes,
        ];
    }

    private function normalize(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
