<?php

namespace App\Modules\Shared\I18n;

use Illuminate\Support\Facades\Config;

/**
 * قراءة نصوص الإعداد مترجَمةً.
 *
 * لماذا هنا لا داخل ملف الإعداد نفسه؟
 *
 * لأن `__()` داخل `config/brand.php` تُنفَّذ لحظة تحميل الإعداد — أي أثناء
 * إقلاع التطبيق، قبل أن يعمل وسيط اللغة أصلًا. والأسوأ: `config:cache`
 * يخبز الناتج في ملف واحد، فتتجمّد لغة الموقع كله على لغة لحظة التخزين.
 * فالترجمة تقع عند **القراءة** لا عند التعريف.
 *
 * ما يُترجَم: كل نصّ يحتوي حرفًا عربيًّا. وما عداه — الروابط، وأسماء
 * الأصناف، ومفاتيح الأيقونات — يمرّ كما هو، لأنه عقد لا نصّ.
 */
final class TranslatedConfig
{
    private const ARABIC = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::translate(Config::get($key, $default));
    }

    private static function translate(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_match(self::ARABIC, $value) === 1 ? __($value) : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        // المفاتيح لا تُترجَم أبدًا: القالب يقرأ `$brand['cases']` بالاسم،
        // وترجمة المفتاح تُفرغ الصفحة بلا خطأ واحد في السجل.
        return array_map(static fn ($item) => self::translate($item), $value);
    }
}
