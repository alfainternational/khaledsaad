<?php

namespace App\Domain\AI\Web;

use Illuminate\Support\Str;

/**
 * يحوّل البحث الحيّ من ميزة منفصلة إلى وقود يرفع ذكاء الأدوات.
 *
 * للأدوات المعتمدة على بيانات السوق، يبني استعلاماً من مدخلات المستخدم + ملف
 * المساحة، يبحث حيّاً (WebResearchService مع كاش)، ويعيد إشارات سوق جاهزة
 * للحقن في سياق التحليل وفي prompt الـ LLM. يتدهور بأمان (يعيد null).
 */
class WebContextEnricher
{
    /** الأدوات التي يفيدها سياق حيّ + الكلمة الدالة للاستعلام. */
    private const TOOL_QUERIES = [
        'market-analysis' => 'اتجاهات السوق ونمو القطاع',
        'competitor-analysis' => 'أبرز المنافسين والبدائل',
        'pricing-strategy' => 'متوسط الأسعار ونماذج التسعير',
        'positioning' => 'تموضع العلامات والتمايز في السوق',
    ];

    public function __construct(private readonly WebResearchService $research) {}

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>|null
     */
    public function enrich(string $toolCode, array $inputs, array $profile, string $projectName): ?array
    {
        if (! (bool) config('services.web_search.enrich_tools', true)) {
            return null;
        }

        if (! isset(self::TOOL_QUERIES[$toolCode])) {
            return null;
        }

        $query = $this->buildQuery($toolCode, $inputs, $profile, $projectName);
        if ($query === '') {
            return null;
        }

        $data = $this->research->research($query, 2);
        $findings = array_slice((array) ($data['findings'] ?? []), 0, 3);
        if ($findings === []) {
            return null;
        }

        return [
            'query' => $query,
            'summary' => (string) ($data['summary'] ?? ''),
            'categories' => $data['categories'] ?? [],
            'findings' => array_map(fn (array $f): array => [
                'title' => (string) ($f['title'] ?? ''),
                'url' => (string) ($f['url'] ?? ''),
                'snippet' => (string) ($f['snippet'] ?? ''),
                'category' => (string) ($f['category'] ?? 'عام'),
            ], $findings),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $profile
     */
    private function buildQuery(string $toolCode, array $inputs, array $profile, string $projectName): string
    {
        $audience = $this->str($profile['audience'] ?? '');
        $country = $this->str($profile['country'] ?? '');

        // أوّل مدخل نصّي ذي معنى من المستخدم (ما عدا brief).
        $firstInput = '';
        foreach ($inputs as $key => $value) {
            if ($key === 'brief' || ! is_string($value)) {
                continue;
            }
            $v = trim($value);
            if (mb_strlen($v) >= 3) {
                $firstInput = $v;
                break;
            }
        }

        $seed = $audience !== '' ? $audience : ($firstInput !== '' ? $firstInput : $this->str($projectName));
        if ($seed === '') {
            return '';
        }

        $parts = array_filter([
            $seed,
            self::TOOL_QUERIES[$toolCode],
            $country,
        ]);

        return Str::limit(trim(implode(' ', $parts)), 120, '');
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
