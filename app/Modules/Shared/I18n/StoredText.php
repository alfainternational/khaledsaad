<?php

namespace App\Modules\Shared\I18n;

/**
 * نصّ يسكن قاعدة البيانات ويُعرض مترجَمًا.
 *
 * أسماء الباقات وعناصر الميزات نصوص واجهة بكل معنى — يقرأها العميل في صفحة
 * الفوترة — لكنها لا تسكن قالبًا فلا يراها ماسح القوالب. فكانت تصل الشاشة
 * الإنجليزية عربيةً وحدها بين سطور مترجَمة، بلا خطأ واحد يُنبِّه.
 *
 * المفتاح هو النصّ العربي نفسه (§١٤)، فما لم تُخبز له ترجمة يُعرض كما هو —
 * وهو السلوك المطلوب لا عطل: ميزة أنشأها الآدمن بعد آخر بناء تظهر بلغته،
 * ويعدّها `i18n:audit` ناقصةً بدل أن تُملأ بتخمين.
 *
 * ما لا يحمل حرفًا عربيًّا يمرّ كما هو: `llms.txt` و`GEO` و`PDF` أسماء لا
 * تُترجَم، ومعجم `keep` في `config/locales.php` يحرسها في القوالب.
 */
final class StoredText
{
    private const ARABIC = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    public static function of(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match(self::ARABIC, $value) !== 1) {
            return $value;
        }

        return __($value);
    }
}
