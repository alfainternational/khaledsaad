<?php

namespace App\Support\Messaging;

use App\Models\MessageVariant;

/**
 * تسميات حالة الإصدار — مصدر واحد للويب والتطبيق والـ API.
 *
 * «بلا رسالة» ليست حالة مخزَّنة بل غياب إصدار: تُميَّز عن «مسودة» لأن
 * الفرق بينهما هو الفرق بين لم يبدأ ولم يُختبر.
 */
class MessageStatus
{
    public static function label(?string $status): string
    {
        return match ($status) {
            MessageVariant::STATUS_DRAFT => 'مسودة',
            MessageVariant::STATUS_TESTED => 'اختُبرت',
            MessageVariant::STATUS_APPROVED => 'معتمدة',
            MessageVariant::STATUS_ARCHIVED => 'مؤرشفة',
            default => 'بلا رسالة',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            MessageVariant::STATUS_DRAFT => 'مسودة',
            MessageVariant::STATUS_TESTED => 'اختُبرت',
            MessageVariant::STATUS_APPROVED => 'معتمدة',
            MessageVariant::STATUS_ARCHIVED => 'مؤرشفة',
        ];
    }
}
