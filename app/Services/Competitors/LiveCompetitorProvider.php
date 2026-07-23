<?php

namespace App\Services\Competitors;

use App\Contracts\CompetitorProvider;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * اكتشاف مرشّحين إقليميين: من يظهر حين يبحث عميلك عن فئتك في سوقك.
 *
 * المصدر: نتائج بحث حقيقية (SERP) لكلمة «فئتك + بلدك». النطاقات الظاهرة
 * منافسون رقميون محتملون — نُرجعهم كمرشّحين لا كحقائق، ويؤكدهم المستخدم.
 *
 * لا يُستدعى داخل الطلب: يُشغَّل من أمر مجدول، ونتيجته تُخزَّن كمرشّحين.
 * تعطّل المصدر أو غياب المفتاح يعيد قائمة فارغة — لا اختلاق أسماء.
 */
class LiveCompetitorProvider implements CompetitorProvider
{
    private const OWN_DOMAINS = ['facebook.com', 'instagram.com', 'youtube.com', 'twitter.com', 'x.com', 'tiktok.com', 'linkedin.com', 'wikipedia.org', 'google.com'];

    public function isAvailable(): bool
    {
        return (bool) config('benchmarks.live_enabled')
            && (config('benchmarks.live.api_key') || config('benchmarks.live.login'));
    }

    /**
     * @return array<int, array{name: string, url: string, tier: string, note: string}>
     */
    public function discover(Project $project): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $industry = $project->industry ?? $project->profile?->description;
        $geography = $project->profile?->geography;

        if (blank($industry)) {
            return [];
        }

        try {
            $domains = $this->fetchRankingDomains(trim($industry.' '.$geography));
        } catch (Throwable $exception) {
            Log::warning('تعذر اكتشاف منافسين مرشّحين', ['project' => $project->id, 'error' => $exception->getMessage()]);

            return [];
        }

        return collect($domains)
            ->reject(fn (string $domain) => $this->isOwnedOrGeneric($domain, $project))
            ->unique()
            ->take(6)
            ->map(fn (string $domain) => [
                'name' => $domain,
                'url' => "https://{$domain}",
                'tier' => ProjectCompetitor::TIER_REGIONAL,
                'note' => 'ظهر في نتائج البحث عن فئتك — مرشّح يحتاج تأكيدك.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function fetchRankingDomains(string $keyword): array
    {
        $config = config('benchmarks.live');

        $response = Http::timeout($config['timeout'])
            ->when(
                $config['login'] !== null,
                fn ($request) => $request->withBasicAuth($config['login'], (string) $config['password']),
                fn ($request) => $request->withToken((string) $config['api_key']),
            )
            ->post(rtrim((string) $config['base_url'], '/').'/v3/serp/google/organic/live/advanced', [[
                'keyword' => $keyword,
                'language_code' => 'ar',
                'depth' => 20,
            ]]);

        if (! $response->successful()) {
            return [];
        }

        return collect(data_get($response->json(), 'tasks.0.result.0.items', []))
            ->map(fn ($item) => (string) data_get($item, 'domain'))
            ->filter()
            ->values()
            ->all();
    }

    private function isOwnedOrGeneric(string $domain, Project $project): bool
    {
        $domain = strtolower($domain);

        foreach (self::OWN_DOMAINS as $generic) {
            if (str_contains($domain, $generic)) {
                return true;
            }
        }

        // نطاق المستخدم نفسه ليس منافسًا.
        $own = $project->profile?->website;

        return $own !== null && str_contains($own, $domain);
    }
}
