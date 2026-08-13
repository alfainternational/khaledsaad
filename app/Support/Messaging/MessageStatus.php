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
            MessageVariant::STATUS_DRAFT => __('مسودة'),
            MessageVariant::STATUS_TESTED => __('اختُبرت'),
            MessageVariant::STATUS_APPROVED => __('معتمدة'),
            MessageVariant::STATUS_ARCHIVED => __('مؤرشفة'),
            default => __('بلا رسالة'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            MessageVariant::STATUS_DRAFT => __('مسودة'),
            MessageVariant::STATUS_TESTED => __('اختُبرت'),
            MessageVariant::STATUS_APPROVED => __('معتمدة'),
            MessageVariant::STATUS_ARCHIVED => __('مؤرشفة'),
        ];
    }
}
