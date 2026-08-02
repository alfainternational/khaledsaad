<?php

namespace App\Support\Messaging;

/**
 * قنوات النشر التي يكتب لها الاستوديو.
 *
 * الحد ليس تجميلًا: إعلان بطول رسالة بريد يُقتطع في المنصة فيضيع نداء
 * الإجراء، فيُفرض الحد على النموذج وعلى المستخدم معًا لا على أحدهما.
 */
enum MessageChannel: string
{
    case Ad = 'ad';
    case Social = 'social';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Landing = 'landing';

    public function label(): string
    {
        return match ($this) {
            self::Ad => 'إعلان',
            self::Social => 'منشور اجتماعي',
            self::Whatsapp => 'واتساب',
            self::Email => 'بريد إلكتروني',
            self::Landing => 'وصف صفحة هبوط',
        };
    }

    /**
     * الحد الأقصى بالمحارف كما تفرضه المنصة عمليًّا.
     */
    public function maxLength(): int
    {
        return match ($this) {
            self::Ad => 180,
            self::Social => 400,
            self::Whatsapp => 320,
            self::Email => 600,
            self::Landing => 300,
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Ad => 'سطران على الأكثر: وعد واحد ونداء إجراء واحد.',
            self::Social => 'افتتاحية توقف التمرير، ثم القيمة، ثم دعوة واحدة.',
            self::Whatsapp => 'رسالة شخصية قصيرة كأنها من إنسان لا من نظام.',
            self::Email => 'سطر أول يبرر الفتح، ثم القيمة والدليل، ثم إجراء واحد.',
            self::Landing => 'جملة عنوان وجملة توضيح — لا فقرة تسويقية.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
