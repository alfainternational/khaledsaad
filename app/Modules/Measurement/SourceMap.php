<?php

namespace App\Modules\Measurement;

use App\Modules\AiReadiness\Models\PresenceProbe;
use App\Modules\AiReadiness\Models\PresenceRun;
use Illuminate\Support\Collection;

/**
 * خريطة المصادر العربية — أحد أصول الخندق (CLAUDE.md §٣).
 *
 * تجيب على «أين أنشر لأظهر»: أي المواقع تستشهد بها النماذج حين تُسأل أسئلة
 * هذا القطاع. لا يملكها منافس عالمي ولا تُبنى بـprompt — تتراكم من كل دورة
 * استطلاع تُشغَّل على المنصة.
 *
 * تُحسب من الاستشهادات المحفوظة وحدها: لا شبكة ولا نموذج، فالخريطة قابلة
 * لإعادة الإنتاج وللتصحيح بأثر رجعي.
 */
class SourceMap
{
    /**
     * المصادر مرتَّبة بالوزن.
     *
     * الوزن هو عدد المحاولات التي استُشهد فيها بالمصدر لا عدد الروابط: موقع
     * ذُكرت منه خمس صفحات في محاولة واحدة ليس أقوى من موقع ذُكرت صفحة واحدة
     * منه في خمس محاولات — الثاني هو المرجع الفعلي للنموذج.
     *
     * @param  Collection<int, PresenceRun>|array<int, PresenceRun>  $runs
     * @return array<string, mixed>
     */
    public function build(iterable $runs, ?string $ownSite = null): array
    {
        $weights = [];
        $attempts = 0;

        foreach ($runs as $run) {
            foreach ($run->probes()->where('status', PresenceProbe::STATUS_OK)->get() as $probe) {
                $attempts++;

                foreach ($this->hostsOf($probe) as $host) {
                    $weights[$host] = ($weights[$host] ?? 0) + 1;
                }
            }
        }

        if ($attempts === 0) {
            return ['available' => false, 'reason' => 'لا استطلاع مكتمل بعد.', 'sources' => []];
        }

        arsort($weights);
        $ownHost = $this->hostOf($ownSite);

        $sources = [];
        foreach ($weights as $host => $count) {
            $sources[] = [
                'host' => $host,
                'citations' => $count,

                // الأساس مع الرقم دائمًا (§١٣): «١٢ من ٦٠ محاولة» لا «١٢».
                'share' => round($count / $attempts, 4),
                'is_own' => $ownHost !== null && strcasecmp($host, $ownHost) === 0,
            ];
        }

        return [
            'available' => true,
            'attempts' => $attempts,
            'sources' => $sources,

            /*
             * موقع العميل بين المصادر أو غيابه عنها هو الجواب المباشر على
             * «هل النماذج تعرفني كمرجع؟» — ويُعرض صراحةً لا يُترك للقارئ
             * ليبحث عن نفسه في قائمة.
             */
            'own_site_ranked' => $this->rankOf($sources, $ownHost),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     */
    private function rankOf(array $sources, ?string $ownHost): ?int
    {
        if ($ownHost === null) {
            return null;
        }

        foreach ($sources as $index => $source) {
            if ($source['is_own']) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * مضيفو هذه المحاولة، بلا تكرار.
     *
     * @return array<int, string>
     */
    private function hostsOf(PresenceProbe $probe): array
    {
        $hosts = [];

        foreach ((array) $probe->citations as $url) {
            $host = $this->hostOf((string) $url);

            if ($host !== null) {
                $hosts[$host] = true;
            }
        }

        return array_keys($hosts);
    }

    private function hostOf(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        $host = preg_replace('/^www\./i', '', (string) $host);

        return $host === '' ? null : mb_strtolower($host);
    }
}
