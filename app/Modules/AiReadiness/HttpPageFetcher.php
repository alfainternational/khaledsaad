<?php

namespace App\Modules\AiReadiness;

use App\Modules\AiReadiness\Contracts\PageFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * جالب الصفحات عبر HTTP.
 *
 * مهلة صريحة ومحاولة إعادة محدودة (§١٤): موقع بطيء أو معطّل يجب أن يُنهي
 * التدقيق بنتيجة «تعذّر الوصول» لا أن يعلّق مهمة في الطابور إلى الأبد.
 *
 * الحد الأعلى للحجم يمنع صفحة ضخمة من استهلاك ذاكرة العامل؛ التدقيق يقرأ
 * الرأس والبنية، ولا يحتاج أكثر من ذلك.
 */
class HttpPageFetcher implements PageFetcher
{
    private const TIMEOUT_SECONDS = 12;

    private const RETRIES = 2;

    private const MAX_BYTES = 2_000_000;

    public function get(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KhaledSaadReadinessBot/1.0 (+https://khaledsaad.net)',
                'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.5',
            ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->retry(self::RETRIES, 400, throw: false)
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return substr($response->body(), 0, self::MAX_BYTES);
        } catch (Throwable $exception) {
            // يُسجَّل المصدر بلا تفاصيل قد تحمل بيانات العميل.
            Log::warning(__('تعذّر جلب صفحة للتدقيق.'), [
                'url' => $url,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
