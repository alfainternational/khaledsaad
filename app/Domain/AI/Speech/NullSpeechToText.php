<?php

namespace App\Domain\AI\Speech;

/**
 * منفّذ فارغ آمن — يُستخدم عند تعطيل الصوت أو غياب مزوّد مدعوم.
 * لا يفرّغ شيئاً ويُعلن أنه غير متاح، فتُخفى واجهة الصوت تلقائياً.
 */
class NullSpeechToText implements SpeechToTextContract
{
    public function transcribe(string $audioContents, string $filename, ?string $language = null): ?string
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
