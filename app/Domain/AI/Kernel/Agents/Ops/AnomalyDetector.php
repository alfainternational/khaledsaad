<?php

namespace App\Domain\AI\Kernel\Agents\Ops;

/**
 * كاشف الشذوذ — نواة وكيل performance-monitor محلياً.
 *
 * إحصاء نقي: يرصد النقاط التي تبعد أكثر من عتبة انحرافات معيارية عن المتوسّط
 * (افتراضياً 2σ)، على سلسلة زمنية من المقاييس. بلا مورد خارجي؛ يُستدعى من أمر
 * cron لاحقاً بلا worker دائم. (قدرة hidden — flag monitoring.)
 */
class AnomalyDetector
{
    private const MIN_POINTS = 4;

    /**
     * @param  array<int, int|float>  $series
     * @return array{status: string, mean: float, std: float, anomalies: array<int, array{index: int, value: float, z: float}>}
     */
    public function detect(array $series, float $threshold = 2.0): array
    {
        $values = array_values(array_map('floatval', $series));
        $n = count($values);

        if ($n < self::MIN_POINTS) {
            return ['status' => 'insufficient_data', 'mean' => 0.0, 'std' => 0.0, 'anomalies' => []];
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn (float $v): float => ($v - $mean) ** 2, $values)) / $n;
        $std = sqrt($variance);

        $anomalies = [];
        if ($std > 0.0) {
            foreach ($values as $i => $v) {
                $z = ($v - $mean) / $std;
                if (abs($z) > $threshold) {
                    $anomalies[] = ['index' => $i, 'value' => $v, 'z' => round($z, 2)];
                }
            }
        }

        return [
            'status' => $anomalies === [] ? 'normal' : 'anomaly',
            'mean' => round($mean, 2),
            'std' => round($std, 2),
            'anomalies' => $anomalies,
        ];
    }
}
