<?php

namespace App\Modules\Shared\I18n;

/**
 * تغليف السلاسل العربية المعروضة في شيفرة PHP بـ `__()`.
 *
 * لماذا تعديل الملفات هنا بينما القوالب تُغلَّف عند التصريف؟
 *
 * لأن Blade يمرّ بمصرّف نملك التدخل فيه، وPHP لا يمرّ بشيء. النص الذي
 * يكتبه متحكّم في رسالة نجاح يصل إلى القالب متغيّرًا لا نصًّا، فلا يعرف
 * القالب أنه نصّ يُترجَم. فإما أن يُغلَّف في مصدره، وإما ألّا يُترجَم.
 *
 * والخطر هنا أكبر بكثير من Blade، ولذلك القيود أشدّ:
 *
 * - **تعبير ثابت.** `const X = 'نص'` لا يقبل نداء دالة. تغليفه خطأ
 *   قاتل عند التحميل لا تحذيرًا. نفسه ينطبق على القيمة الافتراضية
 *   لخاصية أو معامل، وعلى وسائط السمات `#[...]`.
 * - **برومبتات النماذج.** نصّ عربي يُرسَل إلى نموذج ليجيب بالعربية ليس
 *   نصّ واجهة. ترجمته تغيّر ما يُطلب من النموذج لا ما يراه المستخدم.
 * - **معاجم المطابقة.** §١٢ يمنع ترجمة أسماء المقاييس، ومنطق المطابقة
 *   العربية يعتمد على السلاسل نفسها.
 *
 * ولذلك هذا الصنف لا يُشغَّل على المشروع كله، بل على مسارات مُنتقاة
 * صراحةً، ونتيجته تُفحَص بـ`php -l` قبل قبولها.
 */
final class PhpSourceTranslator
{
    private const ARABIC = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    /**
     * نداءات سلاسلها مفاتيح بحث أو إعداد لا نصوص معروضة.
     */
    private const LOOKUP_CALLS = [
        '__', 'trans', 'trans_choice', 'lang', 'config', 'env', 'session', 'view', 'route', 'url',
        'data_get', 'data_set', 'in_array', 'array_key_exists', 'array_search', 'array_flip',
        'str_contains', 'str_starts_with', 'str_ends_with', 'preg_match', 'preg_match_all',
        'preg_replace', 'preg_split', 'strpos', 'stripos', 'explode', 'implode', 'where',
        'contains', 'startsWith', 'endsWith', 'has', 'get', 'is', 'matches',
    ];

    /** @var array<string, int> */
    private array $collected = [];

    /** @var array<int, string> */
    private array $concatenated = [];

    public function reset(): void
    {
        $this->collected = [];
        $this->concatenated = [];
    }

    /**
     * سلاسل تُوصَل بمتغيّر بعامل `.` — تُترَك ولا تُغلَّف.
     *
     * سبب تركها: الوصل يثبّت الترتيب. «تذكير بمهمة — » ثم العنوان تعمل
     * بالإنجليزية صدفةً، و«… من » ثم العدد لا تعمل، لأن اللغة الأخرى قد
     * تضع العدد أولًا. الحلّ ليس ترجمة الشظية بل تحويل السطر إلى نائب:
     * `__('من :count', ['count' => $n])`. وذاك قرار كتابة لا استبدال
     * آليّ، فيُبلَّغ عنه ليُفعل بيد لا أن يُخمَّن.
     *
     * @return array<int, string>
     */
    public function concatenated(): array
    {
        return $this->concatenated;
    }

    /**
     * النصوص التي غُلّفت في آخر تشغيل.
     *
     * @return array<string, int>
     */
    public function collected(): array
    {
        return $this->collected;
    }

    /**
     * تغليف شذرة PHP بلا وسم فتح — محتوى `@php … @endphp` في القوالب.
     *
     * تُعالَج بنفس القواعد لا بقواعد أخفّ: الكتلة داخل القالب تحوي
     * مصفوفات مفاتيحها عقود وقيمها نصوص، وهو الفرق الذي يجب أن يُحترم
     * سواء كُتبت في صنف أو في قالب.
     */
    public function rewriteFragment(string $code): string
    {
        $wrapped = $this->rewrite('<?php '.$code);

        return substr($wrapped, strlen('<?php '));
    }

    public function rewrite(string $code): string
    {
        $tokens = token_get_all($code);
        $out = '';
        $count = count($tokens);

        $parens = [];        // مكدّس: هل هذا القوس نداء دالة؟ واسمها
        $skipUntil = null;   // ';' أو ']' — نهاية سياق لا يُلمس
        $bracketDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if ($skipUntil !== null) {
                $out .= $text;

                if ($text === '[') {
                    $bracketDepth++;
                }

                if ($text === ']') {
                    $bracketDepth--;
                }

                if (($skipUntil === ';' && $text === ';')
                    || ($skipUntil === ']' && $text === ']' && $bracketDepth === 0)) {
                    $skipUntil = null;
                }

                continue;
            }

            if (is_array($token)) {
                // `const X = …` وسمات `#[…]`: تعبير ثابت لا يقبل نداءً.
                if ($token[0] === T_CONST) {
                    $skipUntil = ';';
                    $out .= $text;

                    continue;
                }

                if (defined('T_ATTRIBUTE') && $token[0] === T_ATTRIBUTE) {
                    $skipUntil = ']';
                    $bracketDepth = 1;
                    $out .= $text;

                    continue;
                }

                if ($token[0] === T_CONSTANT_ENCAPSED_STRING && preg_match(self::ARABIC, $token[1])) {
                    $out .= $this->maybeWrap($tokens, $i, $parens);

                    continue;
                }

                $out .= $text;

                continue;
            }

            if ($token === '(') {
                $previous = $this->meaningful($tokens, $i, -1);
                $parens[] = preg_match('/^[A-Za-z_\x80-\xff][\w\x80-\xff]*$/', $previous) === 1 ? $previous : null;
            }

            if ($token === ')') {
                array_pop($parens);
            }

            $out .= $text;
        }

        return $out;
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     * @param  array<int, string|null>  $parens
     */
    private function maybeWrap(array $tokens, int $index, array $parens): string
    {
        $literal = is_array($tokens[$index]) ? $tokens[$index][1] : (string) $tokens[$index];

        foreach ($parens as $callee) {
            if ($callee !== null && in_array($callee, self::LOOKUP_CALLS, true)) {
                return $literal;
            }
        }

        $before = $this->meaningful($tokens, $index, -1);
        $after = $this->meaningful($tokens, $index, +1);

        // مفتاح مصفوفة، أو طرف مقارنة، أو ذراع `match`: كلها عقود لا نصوص.
        if (in_array($after, ['=>', '==', '===', '!=', '!==', '<>', '<=>'], true)) {
            return $literal;
        }

        if (in_array($before, ['==', '===', '!=', '!==', '<>', '<=>', 'case', 'match'], true)) {
            return $literal;
        }

        // قيمة افتراضية لمعامل أو خاصية: تعبير ثابت.
        if ($before === '=' && $this->assignsDeclaration($tokens, $index)) {
            return $literal;
        }

        $key = $this->unquote($literal);

        if (trim($key) === '' || ! preg_match(self::ARABIC, $key)) {
            return $literal;
        }

        if ($before === '.' || $after === '.') {
            $this->concatenated[] = $key;

            return $literal;
        }

        $this->collected[$key] = ($this->collected[$key] ?? 0) + 1;

        return "__('".str_replace(['\\', "'"], ['\\\\', "\\'"], $key)."')";
    }

    /**
     * هل الإسناد الذي يسبق السلسلة إعلانُ خاصية أو معامل لا إسنادَ قيمة؟
     *
     * الفرق حاسم: `$x = 'نص'` داخل دالة يقبل `__()`، و`private $x = 'نص'`
     * في جسم الصنف لا يقبله — والثاني خطأ قاتل لا تحذير.
     *
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    private function assignsDeclaration(array $tokens, int $index): bool
    {
        $target = $this->meaningful($tokens, $index, -2);

        // `$x = '…'` — متغيّر عادي داخل دالة، أو خاصية في جسم الصنف.
        // نميّز بما قبله: معدّل رؤية أو نوع يعني إعلانًا.
        $modifier = $this->meaningful($tokens, $index, -3);

        if (in_array(strtolower($modifier), ['public', 'protected', 'private', 'static', 'readonly', 'var'], true)) {
            return true;
        }

        // معامل بقيمة افتراضية: `function f(string $a = '…')`.
        return str_starts_with($target, '$') && in_array(strtolower($modifier), ['string', 'int', 'float', 'bool', 'mixed', '?string'], true);
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    private function meaningful(array $tokens, int $index, int $step): string
    {
        $seen = 0;
        $target = abs($step);
        $direction = $step > 0 ? 1 : -1;
        $count = count($tokens);

        for ($i = $index + $direction; $i >= 0 && $i < $count; $i += $direction) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $seen++;

            if ($seen === $target) {
                return is_array($token) ? $token[1] : $token;
            }
        }

        return '';
    }

    private function unquote(string $literal): string
    {
        $quote = $literal[0] ?? "'";
        $inner = substr($literal, 1, -1);

        return $quote === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
            : stripcslashes($inner);
    }
}
