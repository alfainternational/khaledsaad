<?php

namespace App\Domain\AI\Speech;

/**
 * تجريد تحويل الكلام إلى نص — المرحلة 2 من تطوير الإدخال.
 *
 * كما جُرّد AiGatewayInterface للتوليد، نُجرّد التفريغ الصوتي حتى يُبدَّل المزوّد
 * (Groq سحابي الآن ← Whisper محلّي عند الانتقال لـ VPS) من الإعدادات بلا لمس الكود.
 * أي منفّذ يتدهور بأمان (null) عند غياب المفتاح أو فشل المزوّد.
 */
interface SpeechToTextContract
{
    /**
     * يفرّغ محتوى ملف صوتي إلى نص، أو null عند التعذّر.
     *
     * @param  string  $audioContents  المحتوى الثنائي للملف الصوتي
     * @param  string  $filename        اسم الملف بامتداده (يحدّد نوع الصوت للمزوّد)
     * @param  string|null  $language    كود اللغة (مثل ar) لتحسين دقّة اللهجة
     */
    public function transcribe(string $audioContents, string $filename, ?string $language = null): ?string;

    /**
     * هل المزوّد جاهز فعلاً (مفعّل + مفتاح حاضر)؟ لإخفاء واجهة الصوت عند غيابه.
     */
    public function isAvailable(): bool;
}
