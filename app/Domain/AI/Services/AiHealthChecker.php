<?php

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * فحص اتصال خفيف لمزوّدي الذكاء (لا يستهلك توكنات/رصيد).
 * يتحقق من الجاهزية (مفتاح موجود) + الوصول الشبكي، بكاش 60 ثانية لمنع الإرهاق.
 */
class AiHealthChecker
{
    /**
     * @return array<string, array{label: string, ready: bool, reachable: bool, ms: ?int, note: string}>
     */
    public function check(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = Cache::get('ai_health');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = [
            'gemini' => $this->probe(
                'Gemini',
                (bool) config('services.gemini.key'),
                'https://generativelanguage.googleapis.com/',
            ),
            'nvidia' => $this->probe(
                'NVIDIA NIM',
                (bool) config('services.nvidia.key'),
                rtrim((string) config('services.nvidia.base_url', 'https://integrate.api.nvidia.com/v1'), '/').'/models',
            ),
            'web_search' => $this->probe(
                'البحث الحيّ',
                true,
                'https://html.duckduckgo.com/',
            ),
        ];

        Cache::put('ai_health', $result, now()->addSeconds(60));

        return $result;
    }

    /**
     * @return array{label: string, ready: bool, reachable: bool, ms: ?int, note: string}
     */
    private function probe(string $label, bool $ready, string $url): array
    {
        if (! $ready) {
            return ['label' => $label, 'ready' => false, 'reachable' => false, 'ms' => null, 'note' => 'غير مهيّأ (لا مفتاح)'];
        }

        $started = microtime(true);
        try {
            $response = Http::timeout(6)->connectTimeout(5)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; KhaledSaadHealthCheck/1.0)',
            ])->get($url);
            $ms = (int) round((microtime(true) - $started) * 1000);

            // أي استجابة (حتى 4xx) = الخادم متاح شبكياً.
            return [
                'label' => $label,
                'ready' => true,
                'reachable' => $response->status() > 0,
                'ms' => $ms,
                'note' => 'استجابة HTTP '.$response->status(),
            ];
        } catch (Throwable $e) {
            return [
                'label' => $label,
                'ready' => true,
                'reachable' => false,
                'ms' => null,
                'note' => 'تعذّر الوصول',
            ];
        }
    }
}
