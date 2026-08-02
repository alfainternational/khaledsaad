<?php

namespace App\Support\Messaging;

/**
 * الاسم كما يُعرض: الأول وحده بلا اسم عائلة.
 *
 * لماذا عرضًا لا تعديلًا للمخزَّن: البيانات السابقة لا تُعاد كتابتها، ولوحات
 * مبنية قبل هذه القاعدة تظل صحيحة — يتغيّر ما يُقرأ لا ما حُفظ.
 *
 * ولماذا لا نقطع أول كلمة دائمًا: «أبو خالد» ليست عائلة، و«المتحمس
 * المستعجل» ليست اسمًا أصلًا بل وصفًا لنمط. القاعدة تميّز الثلاثة:
 *
 * - يبدأ بـ«ال» → وصف نمط لا اسم شخص، فيبقى كاملًا.
 * - كنية (أبو/أم/عبد/ابن/بنت) → كلمتان، لأن قطعها يمسخ الاسم.
 * - ما عدا ذلك → الكلمة الأولى.
 */
class PersonaName
{
    /** كنى ومركّبات لا تُقطع عن تاليها. */
    private const COMPOUND_PREFIXES = ['أبو', 'أبا', 'أبي', 'أم', 'عبد', 'ابن', 'بنت'];

    public static function display(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'شخصية';
        }

        $parts = preg_split('/\s+/u', $name) ?: [$name];

        if (count($parts) === 1) {
            return $parts[0];
        }

        // «المتحمس المستعجل» وصفٌ لا اسم: قطعه يفقده معناه.
        if (str_starts_with($parts[0], 'ال')) {
            return $name;
        }

        if (in_array($parts[0], self::COMPOUND_PREFIXES, true)) {
            return $parts[0].' '.$parts[1];
        }

        return $parts[0];
    }
}
