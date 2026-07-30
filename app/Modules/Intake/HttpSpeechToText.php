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
     * الامتدادات التي تقبلها الواجهة المتوافقة مع OpenAI.
     *
     * القائمة ليست تجميلًا: المزوّد يبوّب الملف بامتداده قبل أن ينظر في بايتاته،
     * ويرفض ما ليس في هذه القائمة برسالة `Invalid file format`.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_EXTENSIONS = ['flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm'];

    /**
     * نوع المحتوى ← امتداد مقبول.
     *
     * الحاويات تُصنَّف بأسماء متعددة حسب النظام: تسجيل صوتي داخل حاوية WebM
     * يقرؤه finfo على أنه `video/webm` لا `audio/webm`، وAAC داخل m4a يظهر
     * `video/mp4` على بعض الأنظمة. لو اعتمدنا على البادئة `audio/` وحدها لسقط
     * تسجيل صحيح.
     *
     * @var array<string, string>
     */
    private const EXTENSION_BY_MIME = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mpga' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/aac' => 'm4a',
        'audio/x-hx-aac-adts' => 'm4a',
        'video/mp4' => 'mp4',
        'audio/wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/vnd.wave' => 'wav',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'video/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/flac' => 'flac',
        'audio/x-flac' => 'flac',
    ];

    /**
     * @return array{text: string, duration_seconds: float, cost_usd: float}
     */
    public function transcribe(string $path, string $locale = 'ar', ?string $filename = null): array
    {
        $key = (string) config('services.speech.key');

        if ($key === '') {
            throw new RuntimeException('لم يُضبط مفتاح خدمة النسخ الصوتي بعد.');
        }

        $response = Http::withToken($key)
            ->timeout(self::TIMEOUT)
            ->retry(self::RETRIES, 1000, throw: false)
            ->attach('file', file_get_contents($path), $this->uploadName($path, $filename))
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
     * اسم يُرفع به الملف، امتداده مقبول لدى المزوّد.
     *
     * **هذا كان عطل الاستقبال الصوتي كله.** كان الاسم `basename($path)`، والمسار
     * هو الملف المؤقت الذي ينشئه PHP للرفع — أي `phpA1B2.tmp`. فكان كل تسجيل
     * يُرفض عند المزوّد، ويُقرأ الرفض عندنا «تعذّر نسخ التسجيل» بلا سبب ظاهر.
     * الاسم لم يكن تفصيلًا شكليًّا: هو ما يبوّب به المزوّد الملف.
     *
     * الاسم الذي يعلنه العميل لا يُستخدم كما هو — يُستخرج منه الامتداد وحده،
     * ويُبنى الأساس عندنا. اسم يأتي من المتصفح مدخلٌ غير موثوق، ولا يُمرَّر إلى
     * ترويسة طلب خارجي بلا تنقية.
     */
    private function uploadName(string $path, ?string $filename): string
    {
        $declared = strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));

        if (in_array($declared, self::SUPPORTED_EXTENSIONS, true)) {
            return 'answer.'.$declared;
        }

        $sniffed = self::EXTENSION_BY_MIME[$this->mimeOf($path)] ?? null;

        if ($sniffed !== null) {
            return 'answer.'.$sniffed;
        }

        /*
         * الرفض هنا لا التخمين: إرسال امتداد مخالف للمحتوى يُرجع الخطأ نفسه من
         * المزوّد بعد أن نكون دفعنا زمن الرفع، ورسالته أغمض من رسالتنا.
         */
        throw new RuntimeException('صيغة التسجيل غير مدعومة. سجّل مرة أخرى أو اكتب إجابتك.');
    }

    private function mimeOf(string $path): string
    {
        if (! function_exists('finfo_open')) {
            return '';
        }

        $info = finfo_open(FILEINFO_MIME_TYPE);

        if ($info === false) {
            return '';
        }

        $mime = finfo_file($info, $path);
        finfo_close($info);

        return is_string($mime) ? strtolower($mime) : '';
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
