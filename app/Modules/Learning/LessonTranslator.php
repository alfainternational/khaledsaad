<?php

namespace App\Modules\Learning;

use App\Modules\Shared\I18n\AiTranslator;
use DOMDocument;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * ترجمة درس كامل مع الحفاظ على بنيته.
 *
 * لماذا ترجمة عُقد النص لا ترجمة الـ HTML كنصّ واحد؟
 *
 * جسم الدرس الواحد يقارب ١٦ ألف محرف من HTML منسّق: أقسام لها `id`
 * تُستعمل في التنقّل الداخلي، وجداول، وأصناف CSS يقرؤها الجافاسكربت.
 * تمرير هذا كلّه إلى نموذج لغوي وطلبُ «ترجمه» يعيد مستندًا مشابهًا لا
 * مطابقًا: `id` يضيع فتنكسر روابط الأقسام، وصنف يُترجَم فيفقد التنسيق،
 * وجدول يُعاد بناؤه بعمود ناقص. والعطل لا يظهر في أي سجل — يظهر للقارئ.
 *
 * فصل النص عن الوسم يجعل النموذج يرى نصًّا فقط: الشجرة تبقى بيدنا،
 * ولا يُستبدل إلا محتوى عقد النص. ما يعود من النموذج لا يمكنه أن يكسر
 * بنية لم يرها أصلًا.
 *
 * والترجمة تمرّ بـ`AiTranslator` نفسه الذي يترجم الواجهة، فيرث منه
 * المعجم المقفل (§١٢: أسماء المقاييس عقد لا نص) وحارس النواب ونبرة
 * كل لغة — بدل معجم ثانٍ يتفرّع عنه بصمت.
 */
class LessonTranslator
{
    public function __construct(private readonly AiTranslator $translator) {}

    /**
     * المَفصِل الوحيد نحو النموذج.
     *
     * غير `final` وواحدٌ عمدًا: منطق الحفاظ على البنية هو الجزء الذي
     * ينكسر بصمت، واختباره يحتاج ترجمة حتمية لا استدعاء شبكة. تمرير كل
     * الطرق من هنا يجعل بديلًا واحدًا في الاختبار يغطيها كلها.
     *
     * @param  array<int, string>  $texts
     * @return array{translations: array<string, string>, failures: array<string, array<int, string>>}
     */
    protected function translateBatch(array $texts, string $locale, string $context): array
    {
        return $this->translator->translate($texts, $locale, array_fill_keys($texts, [$context]));
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<string, string>
     */
    private function translateAll(array $texts, string $locale, string $context): array
    {
        $map = [];
        $batchSize = max(1, (int) config('locales.build.batch', 24));

        foreach (array_chunk($texts, $batchSize) as $batch) {
            $map += $this->translateBatch($batch, $locale, $context)['translations'];
        }

        return $map;
    }

    /**
     * ترجمة جسم الدرس.
     *
     * @return array{html: string, translated: int, missing: int, failures: array<string, array<int, string>>}
     */
    public function translateHtml(string $html, string $locale, string $context = 'lesson'): array
    {
        $document = $this->load($html);
        $nodes = $this->textNodes($document);

        if ($nodes === []) {
            return ['html' => $html, 'translated' => 0, 'missing' => 0, 'failures' => []];
        }

        /*
         * النصّ المُرسَل مقلَّم، والمسافات المحيطة تُعاد بعد الترجمة.
         * إرسالها مع النص يجعل النموذج يبتلعها أو يضاعفها، فتلتصق كلمة
         * بكلمة عبر وسم `<strong>` أو ينفتح فراغ في وسط جملة.
         */
        $originals = [];

        foreach ($nodes as $node) {
            $trimmed = trim($node->nodeValue ?? '');

            if ($trimmed !== '') {
                $originals[] = $trimmed;
            }
        }

        $originals = array_values(array_unique($originals));
        $map = [];
        $failures = [];
        $batchSize = max(1, (int) config('locales.build.batch', 24));

        foreach (array_chunk($originals, $batchSize) as $batch) {
            $result = $this->translateBatch($batch, $locale, $context);

            $map += $result['translations'];
            $failures += $result['failures'];
        }

        $translated = 0;
        $missing = 0;

        foreach ($nodes as $node) {
            $raw = $node->nodeValue ?? '';
            $trimmed = trim($raw);

            if ($trimmed === '') {
                continue;
            }

            if (! isset($map[$trimmed]) || trim((string) $map[$trimmed]) === '') {
                // النصّ الذي تعذّرت ترجمته يبقى بأصله: فراغٌ مكانه يحذف
                // معلومة من الدرس، والأصل العربي أصدق من لا شيء.
                $missing++;

                continue;
            }

            // إسناد مباشر: عقدة النص لا تقبل أبناءً، و`appendChild` عليها
            // تترك العقدة فارغة فيخرج المستند بوسومه كاملة وبلا نص.
            $node->nodeValue = $this->leading($raw).$map[$trimmed].$this->trailing($raw);

            $translated++;
        }

        return [
            'html' => $this->dump($document),
            'translated' => $translated,
            'missing' => $missing,
            'failures' => $failures,
        ];
    }

    /**
     * ترجمة الحقول القصيرة: العنوان والمقتطف ونصوص SEO.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string|null>
     */
    public function translateFields(array $fields, string $locale, string $context = 'lesson.meta'): array
    {
        $texts = array_values(array_unique(array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : '', $fields),
            fn ($value) => $value !== '',
        )));

        if ($texts === []) {
            return $fields;
        }

        $map = $this->translateAll($texts, $locale, $context);

        return array_map(function ($value) use ($map) {
            if (! is_string($value)) {
                return $value;
            }

            return $map[trim($value)] ?? $value;
        }, $fields);
    }

    /**
     * ترجمة كتل `body_json` — نفس النص، لكن التركيب المفهرس يُبقيها
     * متطابقة مع `body_html` بلا إعادة تحليل.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int|string, mixed>
     */
    public function translateBlocks(array $blocks, string $locale): array
    {
        $texts = [];
        $this->collectBlockText($blocks, $texts);
        $texts = array_values(array_unique($texts));

        if ($texts === []) {
            return $blocks;
        }

        return $this->applyBlockText($blocks, $this->translateAll($texts, $locale, 'lesson.block'));
    }

    /** @param array<int|string, mixed> $blocks */
    private function collectBlockText(array $blocks, array &$texts): void
    {
        foreach ($blocks as $key => $value) {
            if (is_array($value)) {
                $this->collectBlockText($value, $texts);

                continue;
            }

            if ($key === 'text' && is_string($value) && trim($value) !== '') {
                $texts[] = trim($value);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $blocks
     * @param  array<string, string>  $map
     * @return array<int|string, mixed>
     */
    private function applyBlockText(array $blocks, array $map): array
    {
        foreach ($blocks as $key => $value) {
            if (is_array($value)) {
                $blocks[$key] = $this->applyBlockText($value, $map);

                continue;
            }

            if ($key === 'text' && is_string($value) && isset($map[trim($value)])) {
                $blocks[$key] = $map[trim($value)];
            }
        }

        return $blocks;
    }

    private function load(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        // نفس التحميل المستعمل في اختبار سلامة الحزمة: أي اختلاف هنا
        // يعطي نصًّا مرئيًّا مختلفًا عمّا يقيسه الاختبار.
        @$document->loadHTML(
            '<?xml encoding="UTF-8"><main>'.$html.'</main>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        return $document;
    }

    /** @return list<DOMText> */
    private function textNodes(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $nodes = [];

        /** @var iterable<DOMNode> $found */
        $found = $xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') ?: [];

        foreach ($found as $node) {
            if ($node instanceof DOMText) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function dump(DOMDocument $document): string
    {
        $main = $document->getElementsByTagName('main')->item(0);

        if ($main === null) {
            return '';
        }

        $html = '';

        foreach ($main->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function leading(string $raw): string
    {
        preg_match('/^\s*/u', $raw, $matches);

        return $matches[0] ?? '';
    }

    private function trailing(string $raw): string
    {
        preg_match('/\s*$/u', $raw, $matches);

        return $matches[0] ?? '';
    }
}
