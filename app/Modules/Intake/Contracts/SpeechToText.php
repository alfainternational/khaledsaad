<?php

namespace App\Modules\Intake\Contracts;

/**
 * نسخ الصوت العربي إلى نص.
 *
 * خلف عقد كبقية المزوّدات (§٤.١)، ولسبب إضافي هنا: النسخ العربي يتفاوت بشدّة
 * بين المزوّدات على اللهجات الخليجية، وتبديل المزوّد قرارُ جودة يُتخذ بعد
 * القياس لا قبله. ربطُ الكود بمزوّد يجعل القرار مستحيلًا.
 */
interface SpeechToText
{
    /**
     * @param  string  $path  مسار ملف الصوت على القرص.
     * @return array{text: string, duration_seconds: float, cost_usd: float}
     */
    public function transcribe(string $path, string $locale = 'ar'): array;

    public function name(): string;
}
