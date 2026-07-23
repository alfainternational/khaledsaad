<?php

namespace App\Services\Tools;

use App\Contracts\BenchmarkProvider;
use App\Models\BenchmarkSnapshot;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * أرقام سوق حيّة: تكلفة النقرة وحجم البحث في مجال المستخدم وبلده.
 *
 * ما نعد به بدقة: هذه أرقام مزاد إعلاني حقيقية من السوق اليوم.
 * ما لا نعد به: أن هذه تكلفة عميل منافس بعينه — هذا رقم داخلي لا يُنشر،
 * وكل ما نفعله هو اشتقاق تقدير معلن أنه تقدير.
 *
 * لا يُستدعى داخل عرض الصفحة: الرقم يُجلب ويُخزَّن، والشاشة تقرأ اللقطة.
 */
class LiveMarketBenchmarks implements BenchmarkProvider
{
    public function isAvailable(): bool
    {
        return (bool) config('benchmarks.live_enabled')
            && (config('benchmarks.live.api_key') || config('benchmarks.live.login'));
    }

    /**
     * @return array{text: string, source: string, is_live?: bool, fetched_at?: string}|null
     */
    public function forField(string $fieldKey, ?Project $project = null): ?array
    {
        $metric = match ($fieldKey) {
            'known_cac' => 'cost_per_customer',
            'monthly_budget' => 'cost_per_click',
            default => null,
        };

        if ($metric === null) {
            return null;
        }

        $snapshot = $this->snapshot($metric, $project);

        if ($snapshot === null) {
            return null;
        }

        $low = (int) round((float) $snapshot->value_low);
        $high = (int) round((float) $snapshot->value_high);

        $text = $metric === 'cost_per_customer'
            ? "في مجالك وسوقك اليوم، تكلفة الوصول لعميل واحد عبر الإعلان تتراوح تقريبًا بين {$low} و{$high} ريال. الرقم مشتق من تكلفة النقرة الحقيقية في السوق، لا من أرقام منافس بعينه."
            : "متوسط تكلفة النقرة الإعلانية في مجالك اليوم بين {$low} و{$high} ريال. اضربها في عدد النقرات التي تحتاجها شهريًا لتعرف الحد الأدنى المعقول لميزانيتك.";

        return [
            'text' => $text,
            'source' => $snapshot->source_name.' · قيس في '.$snapshot->fetched_at->translatedFormat('j F Y'),
            'is_live' => true,
            'fetched_at' => $snapshot->fetched_at->toDateString(),
        ];
    }

    /**
     * جلب الرقم وتخزينه. تُستدعى من أمر مجدول لا من الطلب.
     */
    public function refresh(string $metric, ?string $industry, ?string $geography, ?string $businessModel): ?BenchmarkSnapshot
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $cpc = $this->fetchCostPerClick($industry, $geography);
        } catch (Throwable $exception) {
            // مصدر خارجي متعطل لا يجوز أن يكسر شاشة المستخدم: نسجّل ونصمت.
            Log::warning('تعذر جلب رقم سوق حيّ', ['metric' => $metric, 'error' => $exception->getMessage()]);

            return null;
        }

        if ($cpc === null) {
            return null;
        }

        [$low, $high] = $metric === 'cost_per_customer'
            ? $this->deriveCustomerCost($cpc, $businessModel)
            : [$cpc['low'], $cpc['high']];

        return BenchmarkSnapshot::updateOrCreate(
            [
                'metric' => $metric,
                'industry' => $industry,
                'geography' => $geography,
                'business_model' => $businessModel,
            ],
            [
                'value_low' => $low,
                'value_high' => $high,
                'unit' => 'SAR',
                'source_name' => 'بيانات مزاد الإعلانات في السوق',
                'source_url' => $cpc['source_url'] ?? null,
                'payload' => $cpc,
                'fetched_at' => now(),
            ],
        );
    }

    private function snapshot(string $metric, ?Project $project): ?BenchmarkSnapshot
    {
        $snapshot = BenchmarkSnapshot::where('metric', $metric)
            ->where('industry', $project?->industry)
            ->where('geography', $project?->profile?->geography)
            ->where('business_model', $project?->profile?->business_model)
            ->first();

        if ($snapshot === null || ! $snapshot->isFresh((int) config('benchmarks.cache_days'))) {
            return null;
        }

        return $snapshot;
    }

    /**
     * تكلفة العميل ≈ تكلفة النقرة ÷ نسبة من ينتهي بالشراء.
     *
     * @param  array{low: float, high: float}  $cpc
     * @return array{0: float, 1: float}
     */
    private function deriveCustomerCost(array $cpc, ?string $businessModel): array
    {
        $rates = config('benchmarks.click_to_customer_rate');
        $rate = $rates[$businessModel] ?? $rates['default'];

        return [$cpc['low'] / $rate, $cpc['high'] / $rate];
    }

    /**
     * @return array{low: float, high: float, source_url?: string}|null
     */
    private function fetchCostPerClick(?string $industry, ?string $geography): ?array
    {
        $config = config('benchmarks.live');

        $response = Http::timeout($config['timeout'])
            ->when(
                $config['login'] !== null,
                fn ($request) => $request->withBasicAuth($config['login'], (string) $config['password']),
                fn ($request) => $request->withToken((string) $config['api_key']),
            )
            ->post(rtrim((string) $config['base_url'], '/').'/v3/keywords_data/google_ads/search_volume/live', [[
                'keywords' => array_filter([$industry, $geography ? "{$industry} {$geography}" : null]),
                'language_code' => 'ar',
            ]]);

        if (! $response->successful()) {
            return null;
        }

        $items = data_get($response->json(), 'tasks.0.result', []);
        $bids = collect($items)
            ->flatMap(fn ($item) => array_filter([
                data_get($item, 'low_top_of_page_bid'),
                data_get($item, 'high_top_of_page_bid'),
            ]))
            ->filter()
            ->values();

        if ($bids->isEmpty()) {
            return null;
        }

        return [
            'low' => (float) $bids->min(),
            'high' => (float) $bids->max(),
            'source_url' => null,
        ];
    }
}
