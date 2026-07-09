<?php

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Cache;

/**
 * عدّادات خفيفة لطبقة الذكاء (كاش/بحث) — بلا جدول، تعيش في الكاش.
 * تُستخدم لمراقبة نسبة إصابة الكاش ونشاط البحث في لوحة الآدمن.
 */
class AiMetrics
{
    private const PREFIX = 'ai_metrics:';

    private const TTL_DAYS = 30;

    public function incr(string $metric, int $by = 1): void
    {
        $key = self::PREFIX.$metric;
        Cache::add($key, 0, now()->addDays(self::TTL_DAYS));
        Cache::increment($key, $by);
    }

    public function get(string $metric): int
    {
        return (int) Cache::get(self::PREFIX.$metric, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $hit = $this->get('cache.hit');
        $miss = $this->get('cache.miss');
        $total = $hit + $miss;

        return [
            'cache_hit' => $hit,
            'cache_miss' => $miss,
            'cache_hit_rate' => $total > 0 ? (int) round($hit / $total * 100) : 0,
            'web_search' => $this->get('web.search'),
            'web_fail' => $this->get('web.fail'),
            'cascade_escalated' => $this->get('cascade.escalated'),
            'cascade_failed' => $this->get('cascade.failed'),
            'judge_calls' => $this->get('judge.calls'),
        ];
    }
}
