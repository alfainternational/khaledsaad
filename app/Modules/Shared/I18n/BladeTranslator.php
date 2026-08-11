<?php

namespace App\Modules\Shared\I18n;

/**
 * مغلّف نصوص Blade: يحوّل النص العربي المكتوب في القالب إلى `__()` وقت
 * الترجمة البرمجية (compile)، لا وقت الطلب.
 *
 * لماذا عند الترجمة البرمجية بدل تعديل الملفات؟
 *
 * في المستودع ١٦٧ قالبًا فيها أكثر من ٣٢٠٠ موضع نص عربي. إعادة كتابتها
 * يدويًا إلى `__('...')` تعني فرقًا (diff) بحجم المشروع كله، وكل سطر منه
 * فرصة كسر صامت في صفحة لا يفتحها أحد إلا بعد شهر. أما التغليف عند
 * الترجمة البرمجية فيترك القوالب كما هي — العربية تبقى مصدر الحقيقة
 * المقروء — ويضيف طبقة البحث عن الترجمة في النسخة المُصرّفة وحدها.
 *
 * والأهم: هذا الصنف نفسه هو المستخرِج. لو كان الاستخراج بمنطق ثانٍ،
 * لانحرف المفتاح المستخرَج عن المفتاح المطلوب وقت العرض، فتظهر ترجمة
 * موجودة في الملف ولا تُعرض أبدًا — وهو عطل لا يكشفه أي اختبار وحدة.
 *
 * ما لا يلمسه: تعليقات Blade، و`@verbatim`، ومحتوى `<script>` و`<style>`
 * و`<pre>` و`<textarea>`. أما كتل `@php` فتُعالَج بقواعد شيفرة PHP نفسها
 * عبر `PhpSourceTranslator`. وما يُترَك يُعدّ ويُعرض في `i18n:extract -v`
 * بدل أن يُبتلع صامتًا.
 */
final class BladeTranslator
{
    /**
     * مقسّم القالب. الترتيب في البدائل هو المنطق: ما يجب تجاهله يُلتقط
     * قبل ما يجب معالجته، وإلا التهم النصُّ التعليقَ الذي بداخله.
     */
    private const TOKENS = '/
        (?(DEFINE)
            (?<balanced>\((?:[^()\'"]++|\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|(?&balanced))*\))
        )
          (?P<comment>\{\{--.*?--\}\})
        | (?P<verbatim>@verbatim\b.*?@endverbatim\b)
        | (?P<phpblock>@php\b(?!\s*\().*?@endphp\b)
        | (?P<inline><\?php.*?\?>)
        | (?P<opaque><(?P<opaquetag>script|style|pre|textarea)\b[^>]*>.*?<\/(?P=opaquetag)\s*>)
        | (?P<raw>\{!!.*?!!\})
        | (?P<echo>\{\{.*?\}\})
        | (?P<directive>@[a-zA-Z]\w*(?:\s*(?&balanced))?)
        | (?P<tag><\/?[a-zA-Z!](?:
                \{\{--.*?--\}\}
              | \{\{.*?\}\}
              | \{!!.*?!!\}
              | "(?:\{\{.*?\}\}|\{!!.*?!!\}|[^"])*"
              | \'(?:\{\{.*?\}\}|\{!!.*?!!\}|[^\'])*\'
              | [^>"\'{(]++
              | (?&balanced)
              | [^>]
          )*+>)
    /sxu';

    /** الحرف العربي: وجوده وحده هو ما يجعل النص مرشّحًا للترجمة. */
    private const ARABIC = '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u';

    /** توجيهات نصّها الثاني معروض للمستخدم، فيُغلَّف. غيرها لا يُلمس. */
    private const TEXT_DIRECTIVES = ['section', 'yield', 'slot'];

    /**
     * وسوم داخل الجملة تُضمّ إليها بدل أن تقطعها.
     *
     * سبب وجودها: «ابدأ <strong>الآن</strong> معنا» ثلاثة نصوص عند
     * المصرّف وجملة واحدة عند القارئ. ترجمة الأجزاء الثلاثة منفصلةً
     * تُنتج ركاكة لا يمكن إصلاحها بمراجعة، لأن المترجم لا يرى الجملة
     * أصلًا. وبإدخال الوسم في المفتاح يرى الجملة كاملة ويضع التشديد
     * حيث تقتضيه لغته.
     *
     * الشرط: بلا سمات. الوسم الحامل لـ`href` أو `class` يحمل معه رابطًا
     * أو عقد تصميم إلى داخل نصّ يُرسَل لمترجم — وذاك ما يجب ألّا يحدث.
     */
    private const INLINE_TAG = '/^<\/?(?:strong|b|em|i|u|s|small|span|mark|code|sub|sup|br|wbr)\s*\/?>$/i';

    /** @var array<string, array<int, string>> المفتاح ← المواضع التي ورد فيها */
    private array $collected = [];

    private string $context = '';

    /** @var array<int, array{path: string, reason: string, snippet: string}> */
    private array $skipped = [];

    public function __construct(private readonly PhpSourceTranslator $php = new PhpSourceTranslator) {}

    /**
     * يُرجع القالب بعد تغليف نصوصه. يُستدعى من مصرّف Blade لكل ملف مرة
     * واحدة، ثم تُخزَّن النتيجة في كاش القوالب.
     */
    public function rewrite(string $template, string $context = ''): string
    {
        $this->context = $context;

        $tokens = $this->tokenize($template);
        $out = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            // مجموعة الجملة: نصٌّ وما يتخلله من `{{ }}` بلا وسم بينها.
            // دمجها ضروري لأن «مرحبًا {{ $name }} بك» جملة واحدة عند
            // المترجم وثلاثة أجزاء عند المصرّف؛ ترجمة الأجزاء منفصلةً
            // تُنتج ركاكة لا تُصلَح بمراجعة لاحقة.
            if ($this->partOfSentence($token)) {
                $run = [];

                while ($i < $count && $this->partOfSentence($tokens[$i])) {
                    $run[] = $tokens[$i];
                    $i++;
                }

                $i--;

                $out .= $this->rewriteRun($run);

                continue;
            }

            $out .= match ($token['type']) {
                'tag' => $this->rewriteTag($token['value']),
                'directive' => $this->rewriteDirective($token['value']),
                'raw' => $this->wrapPhpLiterals($token['value']),
                'phpblock' => $this->rewritePhpBlock($token['value']),
                default => $this->note($token),
            };
        }

        return $out;
    }

    /**
     * الاستخراج: نفس المسار تمامًا، والمخرَج يُهمَل. الضمانة الوحيدة
     * لتطابق المفتاح المستخرَج مع المفتاح المطلوب وقت العرض.
     *
     * @return array<string, array<int, string>>
     */
    public function extract(string $template, string $context = ''): array
    {
        $this->collected = [];
        $this->skipped = [];

        $this->rememberPreWrapped($this->rewrite($template, $context));

        return $this->collected;
    }

    /**
     * النصوص المغلَّفة يدويًّا في القالب: `{{ __('نصّ') }}` مكتوبةً بيد كاتب
     * القالب لا بالمغلّف الآلي.
     *
     * سبب وجود هذه الخطوة: المغلّف يتعرّف على `__('…')` ويتركها كما هي حتى
     * لا يُنتج `__(__('…'))` — وكان يتركها **دون تسجيل**. فكانت النتيجة أن
     * التغليف اليدوي — وهو المخرج الوحيد لجملة تحمل متغيّرًا مثل
     * `__('أُضيف :name')` — يُنتج نصًّا لا يدخل الكتالوج أصلًا، فلا يُترجَم
     * أبدًا ولا يظهر في عدّاد النقص. عطلٌ يبدو نجاحًا: القالب يقرأ كأنه
     * مترجَم، والشاشة تبقى عربية.
     *
     * المسح على المخرَج لا على المصدر عمدًا: هناك يستوي ما غلّفه المغلّف
     * وما كُتب باليد، فيمرّ الاثنان بالقاعدة نفسها في موضع واحد.
     */
    private function rememberPreWrapped(string $rewritten): void
    {
        if (preg_match_all(
            "/\b(?:__|trans)\(\s*'((?:[^'\\\\]|\\\\.)*)'/u",
            $rewritten,
            $matches,
        ) === 0) {
            return;
        }

        foreach ($matches[1] as $literal) {
            $key = str_replace(["\\'", '\\\\'], ["'", '\\'], $literal);

            if (preg_match(self::ARABIC, $key) === 1) {
                $this->remember($key);
            }
        }
    }

    /**
     * ما تُرك بلا معالجة في آخر عملية: مدخلات `i18n:audit`.
     *
     * @return array<int, array{path: string, reason: string, snippet: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    public function resetSkipped(): void
    {
        $this->skipped = [];
    }

    // ── التقسيم ──────────────────────────────────────────────────────

    /**
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $template): array
    {
        $tokens = [];
        $offset = 0;

        if (preg_match_all(self::TOKENS, $template, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return [['type' => 'text', 'value' => $template]];
        }

        foreach ($matches as $match) {
            [$value, $start] = $match[0];

            if ($start > $offset) {
                $tokens[] = ['type' => 'text', 'value' => substr($template, $offset, $start - $offset)];
            }

            $tokens[] = ['type' => $this->nameOf($match), 'value' => $value];
            $offset = $start + strlen($value);
        }

        if ($offset < strlen($template)) {
            $tokens[] = ['type' => 'text', 'value' => substr($template, $offset)];
        }

        return $tokens;
    }

    /**
     * @param  array<int|string, array{0: string, 1: int}>  $match
     */
    private function nameOf(array $match): string
    {
        foreach (['comment', 'verbatim', 'phpblock', 'inline', 'opaque', 'raw', 'echo', 'directive', 'tag'] as $name) {
            if (isset($match[$name]) && $match[$name][1] !== -1 && $match[$name][0] !== '') {
                return $name;
            }
        }

        return 'text';
    }

    // ── الجُمَل ───────────────────────────────────────────────────────

    /**
     * @param  array{type: string, value: string}  $token
     */
    private function partOfSentence(array $token): bool
    {
        return $token['type'] === 'text'
            || $token['type'] === 'echo'
            || ($token['type'] === 'tag' && preg_match(self::INLINE_TAG, $token['value']) === 1);
    }

    /**
     * @param  array<int, array{type: string, value: string}>  $run
     */
    private function rewriteRun(array $run): string
    {
        /*
         * وسم مفتوح خارج الجملة ومغلق داخلها يُنتج `</small>` داخل المفتاح
         * بلا فاتحته. لا يُصلحه المترجم لأنه لا يعرف ما الذي أُغلق، ويظهر
         * للمستخدم حرفيًّا لأن `{{ }}` تهرّب الوسوم. فإن اختلّ التوازن،
         * تعود الوسوم حواجزَ كما كانت.
         */
        if (! $this->tagsBalanced($run)) {
            $out = '';
            $segment = [];

            foreach ($run as $token) {
                if ($token['type'] === 'tag') {
                    $out .= $this->rewriteRun($segment).$token['value'];
                    $segment = [];

                    continue;
                }

                $segment[] = $token;
            }

            return $out.$this->rewriteRun($segment);
        }

        $hasArabicText = false;
        $echoHasArabic = false;

        foreach ($run as $token) {
            if ($token['type'] === 'text' && preg_match(self::ARABIC, $token['value'])) {
                $hasArabicText = true;
            }

            if ($token['type'] === 'echo' && preg_match(self::ARABIC, $token['value'])) {
                $echoHasArabic = true;
            }
        }

        // لا عربية في النص: يبقى كما هو، ويُكتفى بتغليف ما في `{{ }}`.
        if (! $hasArabicText) {
            return implode('', array_map(
                fn (array $t): string => $t['type'] === 'echo' ? $this->wrapPhpLiterals($t['value']) : $t['value'],
                $run,
            ));
        }

        // `{{ }}` يحمل نصًّا عربيًّا بنفسه (شرط ثلاثي مثلًا): لا يصلح
        // نائبًا في جملة، لأن إخفاءه خلف `:v1` يخفي نصًّا يحتاج ترجمة.
        if ($echoHasArabic) {
            return implode('', array_map(
                fn (array $t): string => match ($t['type']) {
                    'echo' => $this->wrapPhpLiterals($t['value']),
                    'text' => $this->wrapText($t['value']),
                    default => $t['value'],
                },
                $run,
            ));
        }

        return $this->wrapSentence($run);
    }

    /**
     * هل وسوم المجموعة متوازنة؟ الفراغية (`<br>`) لا تُحتسب.
     *
     * @param  array<int, array{type: string, value: string}>  $run
     */
    private function tagsBalanced(array $run): bool
    {
        $stack = [];

        foreach ($run as $token) {
            if ($token['type'] !== 'tag') {
                continue;
            }

            if (! preg_match('/^<(\/?)([a-zA-Z]+)/', $token['value'], $m)) {
                return false;
            }

            $name = strtolower($m[2]);

            if (in_array($name, ['br', 'wbr'], true)) {
                continue;
            }

            if ($m[1] === '') {
                $stack[] = $name;

                continue;
            }

            if (array_pop($stack) !== $name) {
                return false;
            }
        }

        return $stack === [];
    }

    /**
     * هل طرفا المجموعة وسمٌ واحد يغلّفها كلها؟ `<em>نصّ</em>` نعم،
     * و`<b>1</b> نصّ` لا.
     *
     * @param  array<int, array{type: string, value: string}>  $run
     */
    private function wrapsWholeRun(array $run): bool
    {
        $first = $run[0];
        $last = $run[count($run) - 1];

        if ($first['type'] !== 'tag' || $last['type'] !== 'tag') {
            return false;
        }

        if (! preg_match('/^<([a-zA-Z]+)/', $first['value'], $open)) {
            return false;
        }

        if (! preg_match('/^<\/([a-zA-Z]+)/', $last['value'], $close)) {
            return false;
        }

        return strtolower($open[1]) === strtolower($close[1])
            && $this->tagsBalanced(array_slice($run, 1, -1));
    }

    /**
     * جملة واحدة من نصوص و`{{ }}`: المتغيّرات تصير `:v1` في المفتاح
     * وتُمرَّر بدائلَ، فيبقى ترتيب الكلمات حرًّا في اللغة الهدف.
     *
     * @param  array<int, array{type: string, value: string}>  $run
     */
    private function wrapSentence(array $run): string
    {
        $lead = '';
        $trail = '';

        /*
         * وسم على الطرف ليس جزءًا من الجملة بل غلافها: `<strong>نص</strong>`
         * مفتاحه «نص». لكن الإخراج مشروط ببقاء التوازن: في
         * `<b>1</b> إنشاء الحساب` إخراجُ `<b>` وحدها يترك `</b>` يتيمة
         * داخل المفتاح — وهي بالضبط الحالة التي تظهر للمستخدم نصًّا خامًّا.
         */
        while (count($run) >= 2 && $this->wrapsWholeRun($run)) {
            $lead .= array_shift($run)['value'];
            $trail = array_pop($run)['value'].$trail;
        }

        while ($run !== [] && $run[0]['type'] === 'tag' && $this->tagsBalanced(array_slice($run, 1))) {
            $lead .= array_shift($run)['value'];
        }

        while ($run !== [] && $run[count($run) - 1]['type'] === 'tag' && $this->tagsBalanced(array_slice($run, 0, -1))) {
            $trail = array_pop($run)['value'].$trail;
        }

        if ($run === []) {
            return $lead.$trail;
        }

        // اقتطاع الأطراف: المسافات والأسطر خارج الترجمة لا داخلها، وإلا
        // اختلف المفتاح باختلاف مسافة بادئة في القالب.
        if ($run[0]['type'] === 'text') {
            preg_match('/^\s*/u', $run[0]['value'], $m);
            $lead .= $m[0];
            $run[0]['value'] = substr($run[0]['value'], strlen($m[0]));
        }

        $lastIndex = count($run) - 1;

        if ($run[$lastIndex]['type'] === 'text') {
            preg_match('/\s*$/u', $run[$lastIndex]['value'], $m);
            $trail = $m[0].$trail;
            $run[$lastIndex]['value'] = substr($run[$lastIndex]['value'], 0, strlen($run[$lastIndex]['value']) - strlen($m[0]));
        }

        $key = '';
        $replacements = [];
        $index = 0;

        foreach ($run as $token) {
            if ($token['type'] === 'text' || $token['type'] === 'tag') {
                $key .= $token['value'];

                continue;
            }

            $expression = trim(substr($token['value'], 2, -2));

            if ($expression === '') {
                continue;
            }

            $index++;
            $key .= ':v'.$index;
            $replacements['v'.$index] = $expression;
        }

        $key = $this->normalizeKey($key);

        if ($key === '' || ! preg_match(self::ARABIC, $key)) {
            return $lead.implode('', array_column($run, 'value')).$trail;
        }

        $this->remember($key);

        /*
         * وسمٌ داخل المفتاح يفرض إخراجًا غير مهرَّب، وإلا عرض المستخدم
         * `&lt;strong&gt;` نصًّا. والنص المترجَم موثوق لأنه من المستودع لا
         * من مُدخَل. أما المتغيّرات فلا يُوثَق بها، فتُهرَّب كلٌّ على حدة
         * بـ`e()` — فتبقى الحماية كما كانت في `{{ }}` بالضبط.
         */
        $unescaped = str_contains($key, '<');

        $pairs = [];

        foreach ($replacements as $name => $expression) {
            $pairs[] = "'".$name."' => ".($unescaped ? 'e('.$expression.')' : $expression);
        }

        $call = "__('".$this->escape($key)."'"
            .($pairs === [] ? '' : ', ['.implode(', ', $pairs).']')
            .')';

        return $unescaped
            ? $lead.'{!! '.$call.' !!}'.$trail
            : $lead.'{{ '.$call.' }}'.$trail;
    }

    private function wrapText(string $text): string
    {
        if (! preg_match(self::ARABIC, $text)) {
            return $text;
        }

        preg_match('/^\s*/u', $text, $lead);
        preg_match('/\s*$/u', $text, $trail);

        $key = $this->normalizeKey($text);

        if ($key === '') {
            return $text;
        }

        $this->remember($key);

        return $lead[0]."{{ __('".$this->escape($key)."') }}".$trail[0];
    }

    // ── الوسوم والتوجيهات ────────────────────────────────────────────

    private function rewriteTag(string $tag): string
    {
        /*
         * ١) ما بداخل الوسم من `{{ }}` — سمة مربوطة أو شرط عرض.
         *
         * التعليق `{{-- … --}}` يُستثنى أولًا لا صدفةً: تعليق مكتوب بين
         * سمات الوسم — وهو أسلوب شائع في هذا المستودع — يبدأ بـ`{{`
         * وينتهي بـ`}}`، فيبدو نداءً. تمريره إلى مغلّف التعابير يمرّر
         * `-- نصّ عربي --` إلى `token_get_all` فيخرج منه PHP معطوب
         * ينكسر عند العرض لا عند التصريف.
         */
        $tag = preg_replace_callback(
            '/\{\{--.*?--\}\}|\{\{.*?\}\}|\{!!.*?!!\}/su',
            fn (array $m): string => str_starts_with($m[0], '{{--')
                ? $m[0]
                : $this->wrapPhpLiterals($m[0]),
            $tag,
        ) ?? $tag;

        // ٢) السمات المربوطة `:attr="تعبير"` — قيمتها PHP لا نص.
        $tag = preg_replace_callback(
            '/(?<=\s):([a-zA-Z][\w-]*)\s*=\s*"([^"]*)"/u',
            function (array $m): string {
                if (! preg_match(self::ARABIC, $m[2])) {
                    return $m[0];
                }

                return ':'.$m[1].'="'.$this->wrapPhpExpression($m[2]).'"';
            },
            $tag,
        ) ?? $tag;

        // ٣) السمات النصية المقروءة من المستخدم أو قارئ الشاشة.
        $attributes = (array) config('locales.scan.blade.attributes', []);

        if ($attributes === []) {
            return $tag;
        }

        $names = implode('|', array_map('preg_quote', $attributes));

        return preg_replace_callback(
            '/(?<=\s)('.$names.')\s*=\s*"([^"]*)"/iu',
            function (array $m): string {
                $value = $m[2];

                if (! preg_match(self::ARABIC, $value)) {
                    return $m[0];
                }

                // قيمة فيها `{{ }}` عولجت في الخطوة الأولى؛ تغليفها مرة
                // ثانية يُنتج `__()` داخل `__()`.
                if (str_contains($value, '{{') || str_contains($value, '{!!') || str_contains($value, '@')) {
                    return $m[0];
                }

                $key = $this->normalizeKey($value);

                if ($key === '') {
                    return $m[0];
                }

                $this->remember($key);

                return $m[1].'="{{ __(\''.$this->escape($key).'\') }}"';
            },
            $tag,
        ) ?? $tag;
    }

    /**
     * كتلة `@php … @endphp`.
     *
     * كانت تُترك كلها، فبقيت مصفوفات الأسئلة الشائعة وبطاقات الصفحات —
     * وهي نصوص يقرأها الزائر — خارج الترجمة. تُعالَج الآن بقواعد شيفرة
     * PHP نفسها: مفتاح المصفوفة عقد، وقيمتها نصّ، وطرف المقارنة لا
     * يُمسّ، والموصول بمتغيّر يُترَك ويُبلَّغ عنه.
     */
    private function rewritePhpBlock(string $block): string
    {
        if (! preg_match(self::ARABIC, $block)) {
            return $block;
        }

        if (! preg_match('/^(@php\b)(.*)(@endphp)$/su', $block, $m)) {
            return $this->note(['type' => 'phpblock', 'value' => $block]);
        }

        $this->php->reset();
        $rewritten = $m[1].$this->php->rewriteFragment($m[2]).$m[3];

        foreach (array_keys($this->php->collected()) as $key) {
            $this->remember((string) $key);
        }

        foreach ($this->php->concatenated() as $fragment) {
            $this->skipped[] = [
                'path' => $this->context,
                'reason' => 'concatenated',
                'snippet' => $this->snippet((string) $fragment),
            ];
        }

        return $rewritten;
    }

    private function rewriteDirective(string $directive): string
    {
        if (! preg_match(self::ARABIC, $directive)) {
            return $directive;
        }

        if (! preg_match('/^@([a-zA-Z]\w*)\s*\((.*)\)$/su', $directive, $m)) {
            return $directive;
        }

        // `@php($x = 'نص')` الشكل المختصر: شذرة PHP لا توجيه عرض.
        if (strtolower($m[1]) === 'php') {
            $this->php->reset();
            $rewritten = '@php('.$this->php->rewriteFragment($m[2]).')';

            foreach (array_keys($this->php->collected()) as $key) {
                $this->remember((string) $key);
            }

            return $rewritten;
        }

        if (! in_array(strtolower($m[1]), self::TEXT_DIRECTIVES, true)) {
            $this->skipped[] = [
                'path' => $this->context,
                'reason' => 'directive:@'.$m[1],
                'snippet' => $this->snippet($directive),
            ];

            return $directive;
        }

        return '@'.$m[1].'('.$this->wrapPhpExpression($m[2]).')';
    }

    /**
     * تُترَك بلا معالجة، وتُسجَّل ليقرأها التدقيق: الصمت هنا يعني نصًّا
     * عربيًّا لن يُترجَم أبدًا دون أن يشتكي أحد.
     *
     * @param  array{type: string, value: string}  $token
     */
    private function note(array $token): string
    {
        if (in_array($token['type'], ['phpblock', 'inline', 'opaque'], true)
            && preg_match(self::ARABIC, $token['value'])) {
            $this->skipped[] = [
                'path' => $this->context,
                'reason' => $token['type'],
                'snippet' => $this->snippet($token['value']),
            ];
        }

        return $token['value'];
    }

    // ── سلاسل PHP ────────────────────────────────────────────────────

    /**
     * تغليف السلاسل العربية داخل `{{ ... }}` أو `{!! ... !!}`.
     */
    private function wrapPhpLiterals(string $echo): string
    {
        if (! preg_match(self::ARABIC, $echo)) {
            return $echo;
        }

        if (str_starts_with($echo, '{!!')) {
            return '{!! '.$this->wrapPhpExpression(trim(substr($echo, 3, -3))).' !!}';
        }

        return '{{ '.$this->wrapPhpExpression(trim(substr($echo, 2, -2))).' }}';
    }

    /**
     * تغليف كل سلسلة عربية حرفية داخل تعبير PHP بـ `__()`.
     *
     * التقطيع بـ `token_get_all` لا بتعبير نمطي، لأن العربية ترد أيضًا في
     * تعليق داخل التعبير، وفي مفتاح مصفوفة، وفي طرف مقارنة. الثلاثة
     * يجب ألّا تُترجَم: ترجمة طرف المقارنة تكسر الشرط نفسه حين تتغيّر
     * اللغة — عطل يظهر في الإنجليزية وحدها ولا يظهر في أي اختبار عربي.
     */
    private function wrapPhpExpression(string $expression): string
    {
        $tokens = @token_get_all('<?php '.$expression.';');

        if ($tokens === []) {
            return $expression;
        }

        $out = '';
        $count = count($tokens);

        /*
         * مكدّس الأقواس: يفرّق بين قوس نداء دالة وقوس تجميع.
         *
         * سببه أن السلسلة العربية داخل نداء دالة ليست نصًّا معروضًا في
         * الغالب بل مفتاح بحث: `data_get($map, 'تعليم')` و`in_array($s,
         * ['تعليم'])`. ترجمتها تكسر المنطق في اللغة الأخرى وحدها. والخطأ
         * المعاكس — ترك نصّ معروض بلا ترجمة — يظهر عربيًّا في شاشة
         * إنجليزية: مزعج ومرئيّ، لا صامت وكاسر.
         */
        $parens = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (! is_array($token)) {
                if ($token === '(') {
                    $previous = $this->meaningful($tokens, $i, -1);
                    $parens[] = $previous !== '' && preg_match('/^[\w\]\)]/u', $previous) === 1;
                }

                if ($token === ')') {
                    array_pop($parens);
                }

                $out .= $token;

                continue;
            }

            if ($token[0] === T_OPEN_TAG) {
                continue;
            }

            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING || ! preg_match(self::ARABIC, $token[1])) {
                $out .= $token[1];

                continue;
            }

            if (in_array(true, $parens, true) || ! $this->literalIsTranslatable($tokens, $i)) {
                $out .= $token[1];

                continue;
            }

            $key = $this->normalizeKey($this->unquote($token[1]));

            if ($key === '' || ! preg_match(self::ARABIC, $key)) {
                $out .= $token[1];

                continue;
            }

            $this->remember($key);
            $out .= "__('".$this->escape($key)."')";
        }

        return rtrim(rtrim($out), ';');
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    private function literalIsTranslatable(array $tokens, int $index): bool
    {
        $before = $this->meaningful($tokens, $index, -1);
        $after = $this->meaningful($tokens, $index, +1);

        // مفتاح مصفوفة: `['تعليم' => ...]` عقد بيانات لا نص واجهة.
        if ($after === '=>') {
            return false;
        }

        // طرف مقارنة أو `case`: ترجمته تكسر المطابقة.
        if (in_array($before, ['==', '===', '!=', '!==', '<>', '<=>', 'case', 'match'], true)) {
            return false;
        }

        if (in_array($after, ['==', '===', '!=', '!==', '<>', '<=>'], true)) {
            return false;
        }

        // مغلَّفة أصلًا: `__('...')` أو `trans('...')`.
        if ($before === '(') {
            $callee = $this->meaningful($tokens, $index, -2);

            if (in_array($callee, ['__', 'trans', 'trans_choice', '@lang'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{0: int, 1: string}|string>  $tokens
     */
    private function meaningful(array $tokens, int $index, int $step): string
    {
        $skipped = 0;
        $target = abs($step);
        $direction = $step > 0 ? 1 : -1;

        for ($i = $index + $direction; $i >= 0 && $i < count($tokens); $i += $direction) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $skipped++;

            if ($skipped === $target) {
                return is_array($token) ? $token[1] : $token;
            }
        }

        return '';
    }

    // ── أدوات ────────────────────────────────────────────────────────

    /**
     * تطبيع المفتاح: المسافات المتتابعة والأسطر تصير مسافة واحدة.
     *
     * سببه أن HTML يطوي المسافات أصلًا، فالنص المعروض واحد سواء كتبه
     * المطوّر في سطر أو خمسة. بلا هذا التطبيع يصير للجملة الواحدة عدة
     * مفاتيح تختلف بمسافة بادئة، فتُترجَم مرارًا وتُدفع تكلفتها مرارًا.
     */
    private function normalizeKey(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function escape(string $key): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $key);
    }

    private function unquote(string $literal): string
    {
        $quote = $literal[0] ?? "'";
        $inner = substr($literal, 1, -1);

        return $quote === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
            : stripcslashes($inner);
    }

    private function remember(string $key): void
    {
        $this->collected[$key] ??= [];

        if ($this->context !== '' && ! in_array($this->context, $this->collected[$key], true)) {
            $this->collected[$key][] = $this->context;
        }
    }

    private function snippet(string $value): string
    {
        $clean = $this->normalizeKey($value);

        return mb_strlen($clean) > 120 ? mb_substr($clean, 0, 117).'…' : $clean;
    }
}
