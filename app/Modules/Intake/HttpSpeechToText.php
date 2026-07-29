<?php

namespace App\Modules\Intake;

use App\Modules\Intake\Contracts\SpeechToText;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * نسخ عبر واجهة متوافقة مع OpenAI (Groq وغيره).
 *
 * المفاتيح من الإعدادات لا من الكود: `SettingsStore` يطبّقها حيًّا، فتبديل
 * المزوّد أو مفتاحه لا يحتاج نشرًا.
 *
 * مهلة صريحة وإعادة محاولة محدودة (§٤.١): ملف صوتي بطيء الرفع يعلّق طلبًا
 * حتى نهاية مهلة PHP لو تُرك بلا حدّ، وإعادة المحاولة بلا سقف تضاعف التكلفة
 * على عطلٍ لن يُصلحه التكرار.
 */
class HttpSpeechToText implements SpeechToText
{
    /** ثوانٍ. النسخ يستغرق جزءًا من مدة التسجيل، ودقيقتان تكفيان لأي مقطع مقبول. */
    private const TIMEOUT = 120;

    /** محاولتان: عطل شبكة عابر يستحق ثانية، وعطل مزوّد لا يُصلحه التكرار. */
    private const RETRIES = 2;

    /**
     * @return array{text: string, duration_seconds: float, cost_usd: float}
     */
    public function transcribe(string $path, string $locale = 'ar'): array
    {
        $key = (string) config('services.speech.key');

        if ($key === '') {
            throw new RuntimeException('لم يُضبط مفتاح خدمة النسخ الصوتي بعد.');
        }

        $response = Http::withToken($key)
            ->timeout(self::TIMEOUT)
            ->retry(self::RETRIES, 1000, throw: false)
            ->attach('file', file_get_contents($path), basename($path))
            ->post(rtrim((string) config('services.speech.base_url'), '/').'/audio/transcriptions', [
                'model' => (string) config('services.speech.model'),

                /*
                 * اللغة تُمرَّر صراحةً: بلا تحديدها يخمّن النموذج، وقد يقرأ
                 * الخليجية إنجليزيةً منطوقة فيعيد نصًّا لا معنى له.
                 */
                'language' => $locale,
                'response_format' => 'verbose_json',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('تعذّر نسخ التسجيل. حاول مرة أخرى أو اكتب إجابتك.');
        }

        $duration = (float) ($response->json('duration') ?? 0.0);

        return [
            'text' => trim((string) $response->json('text')),
            'duration_seconds' => $duration,
            'cost_usd' => $this->costOf($duration),
        ];
    }

    public function name(): string
    {
        return (string) config('services.speech.model', 'speech');
    }

    /**
     * التكلفة بالدقيقة كما يفوتر المزوّد.
     *
     * تُحسب هنا لا في الجامع: السعر خاصيّة مزوّد، ووضعه في الجامع يجعل تبديل
     * المزوّد يترك سعرًا قديمًا يحسب فاتورة جديدة.
     */
    private function costOf(float $seconds): float
    {
        $perMinute = (float) config('services.speech.cost_per_minute', 0.0);

        return round($seconds / 60 * $perMinute, 6);
    }
}
