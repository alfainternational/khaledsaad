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
     * @param  string|null  $filename  الاسم كما أعلنه العميل، لاستخراج الامتداد منه.
     *                                 يُمرَّر لأن المسار على القرص ملفٌ مؤقت باسم
     *                                 `phpXXXX.tmp` لا امتداد صوتي فيه، والمزوّدات
     *                                 المتوافقة مع OpenAI ترفض ما لا امتداد له.
     * @return array{text: string, duration_seconds: float, cost_usd: float}
     */
    public function transcribe(string $path, string $locale = 'ar', ?string $filename = null): array;

    public function name(): string;
}
