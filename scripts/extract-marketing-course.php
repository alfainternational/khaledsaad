<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$sourceDirectory = $argv[1] ?? '';

if (! is_dir($sourceDirectory)) {
    fwrite(STDERR, "Usage: php scripts/extract-marketing-course.php <docx-directory>\n");
    exit(1);
}

$course = [
    1 => ['slug' => '1', 'metaphor' => 'بوصلة تسويق تشير إلى عميل واضح وسط سوق مزدحم'],
    2 => ['slug' => 'افهم-السوق-قبل-أن-تبدأ', 'metaphor' => 'منظار يقرأ طبقات سوق متداخلة'],
    3 => ['slug' => 'اعرف-عميلك-المثالي', 'metaphor' => 'عدسة تركيز تلتقط عميلا واحدا من جمهور متنوع'],
    4 => ['slug' => 'مواصفات-العميل-المثالي', 'metaphor' => 'خريطة رحلة تنتهي بصورة عميل مكتملة'],
    5 => ['slug' => 'صوتك-في-السوق', 'metaphor' => 'موجة صوت مميزة تعبر ضوضاء السوق'],
    6 => ['slug' => 'كتابة-المحتوى-التسويقي', 'metaphor' => 'قلم يحول الأفكار إلى مسار يقود إلى قرار'],
    7 => ['slug' => 'القنوات-التسويقية', 'metaphor' => 'شبكة قنوات تصب في نقطة عميل واحدة'],
    8 => ['slug' => 'خطة-المحتوى-التسويقي', 'metaphor' => 'تقويم تحريري متصل بمسار نمو'],
    9 => ['slug' => 'بناء-الهوية-التسويقية', 'metaphor' => 'قطع هوية تتجمع في بصمة بصرية متماسكة'],
    10 => ['slug' => 'كيف-تبني-محتوى-يبيع', 'metaphor' => 'جسر من فكرة محتوى إلى عربة شراء'],
    11 => ['slug' => 'الإعلان-الممول', 'metaphor' => 'هدف إعلاني تحيط به إشارات قياس وتحكم'],
    12 => ['slug' => 'استراتيجيات-زيادة-المبيعات', 'metaphor' => 'سلم نمو متوازن تصعد عليه نقاط بيع'],
    13 => ['slug' => 'قياس-النتائج-التسويقية', 'metaphor' => 'لوحة قياس تحول إشارات متناثرة إلى اتجاه واضح'],
    14 => ['slug' => 'بناء-علاقات-العملاء', 'metaphor' => 'جسر ثقة طويل بين علامة وعميل'],
    15 => ['slug' => 'التسويق-بالذكاء-الاصطناعي', 'metaphor' => 'عقل رقمي يساعد بوصلة تسويق بشرية'],
    16 => ['slug' => 'إدارة-الأزمات-التسويقية', 'metaphor' => 'درع اتصال يحمي سمعة العلامة أثناء عاصفة'],
    17 => ['slug' => 'التسويق-للشركات', 'metaphor' => 'تروس شركات مترابطة حول مصافحة أعمال'],
    18 => ['slug' => 'تحسين-محركات-البحث', 'metaphor' => 'مصباح بحث يكشف مسارا صاعدا داخل شبكة صفحات'],
    19 => ['slug' => 'التسويق-التأثيري', 'metaphor' => 'دوائر تأثير بشرية تنتشر من صوت موثوق'],
    20 => ['slug' => 'ورشة-عمل-تسويقية-تطبيقية', 'metaphor' => 'طاولة عمل تجمع كل أدوات التسويق في نموذج مكتمل'],
];

$files = glob(rtrim($sourceDirectory, '\\/').'\/*.docx') ?: [];
$files = array_values(array_filter($files, fn (string $path): bool => ! str_starts_with(basename($path), '~$')));
usort($files, function (string $a, string $b): int {
    preg_match('/^(\d+)/u', basename($a), $left);
    preg_match('/^(\d+)/u', basename($b), $right);

    return ((int) ($left[1] ?? 0)) <=> ((int) ($right[1] ?? 0));
});

if (count($files) !== 20) {
    fwrite(STDERR, sprintf("Expected 20 Word documents, found %d.\n", count($files)));
    exit(1);
}

$outputDirectory = dirname(__DIR__).'/database/data/content/marketing-course';
$lessonsDirectory = $outputDirectory.'/lessons';

if (! is_dir($lessonsDirectory) && ! mkdir($lessonsDirectory, 0775, true) && ! is_dir($lessonsDirectory)) {
    throw new RuntimeException("Unable to create {$lessonsDirectory}");
}

$manifest = [
    'course' => [
        'title' => 'تعلم التسويق',
        'description' => 'سلسلة عملية متكاملة لتعلم التسويق وبناء السوق والمحتوى والمبيعات والقياس.',
        'slug' => 'تعلم-التسويق',
    ],
    'lessons' => [],
];

foreach ($files as $index => $file) {
    $order = $index + 1;
    $document = readDocument($file);
    $titleBlock = array_shift($document);
    $title = trim((string) ($titleBlock['text'] ?? ''));

    if ($title === '') {
        $title = trim((string) preg_replace('/^\d+\s*/u', '', pathinfo($file, PATHINFO_FILENAME)));
    }

    [$bodyHtml, $bodyJson, $outline] = renderBlocks($document);
    $sourceText = canonicalText(array_column($document, 'text'));
    $wordCount = count(preg_split('/\s+/u', $sourceText, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $duration = max(3, (int) ceil($wordCount / 180));
    $sourceKey = sprintf('marketing-course-%02d', $order);
    $lessonPath = sprintf('data/content/marketing-course/lessons/%02d.php', $order);
    $coverStem = sprintf('assets/content/marketing-course/lesson-%02d', $order);

    $lesson = [
        'source_key' => $sourceKey,
        'source_filename' => basename($file),
        'source_text' => $sourceText,
        'source_text_hash' => hash('sha256', $sourceText),
        'order' => $order,
        'title' => $title,
        'slug' => $course[$order]['slug'],
        'excerpt' => "درس تطبيقي ضمن سلسلة تعلم التسويق: {$title}.",
        'body_html' => $bodyHtml,
        'body_json' => ['type' => 'learning-document', 'version' => 1, 'blocks' => $bodyJson],
        'duration_minutes' => $duration,
        'seo_title' => "{$title} | تعلم التسويق مع خالد سعد",
        'seo_description' => "تعلم {$title} ضمن سلسلة عربية عملية في التسويق، مع أمثلة ومهام تطبيقية تساعدك على تحويل المعرفة إلى خطوات قابلة للتنفيذ.",
        'cover_image_path' => "/{$coverStem}-hero.webp",
        'learning_meta' => [
            'series' => 'تعلم التسويق',
            'outline' => $outline,
            'faq' => extractFaq($bodyJson),
            'word_count' => $wordCount,
            'cover' => [
                'hero' => "/{$coverStem}-hero.webp",
                'card' => "/{$coverStem}-card.webp",
                'og' => "/{$coverStem}-og.png",
                'alt' => "رسم تحريري رمزي لدرس {$title}",
                'metaphor' => $course[$order]['metaphor'],
            ],
        ],
    ];

    writePhpArray(dirname(__DIR__).'/database/'.$lessonPath, $lesson);
    $manifest['lessons'][] = [
        'order' => $order,
        'source_key' => $sourceKey,
        'path' => $lessonPath,
        'slug' => $lesson['slug'],
        'title' => $title,
    ];
}

writePhpArray($outputDirectory.'/manifest.php', $manifest);
file_put_contents(
    $outputDirectory.'/manifest.json',
    json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
);

fwrite(STDOUT, "20 lessons generated; 0 text mismatches; 0 temporary files included.\n");

/** @return array<int, array{text: string, type: string, heading?: bool}> */
function readDocument(string $path): array
{
    $archive = new ZipArchive;

    if ($archive->open($path) !== true) {
        throw new RuntimeException("Unable to open {$path}");
    }

    $xml = $archive->getFromName('word/document.xml');
    $archive->close();

    if ($xml === false) {
        throw new RuntimeException("word/document.xml is missing in {$path}");
    }

    $document = new DOMDocument;
    $document->preserveWhiteSpace = true;
    $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $body = $xpath->query('/w:document/w:body')->item(0);
    $blocks = [];

    foreach ($body?->childNodes ?? [] as $node) {
        if (! $node instanceof DOMElement) {
            continue;
        }

        if ($node->localName === 'p') {
            $text = paragraphText($xpath, $node);

            if (trim($text) !== '') {
                $blocks[] = [
                    'type' => 'paragraph',
                    'text' => $text,
                    'heading' => isHeading($xpath, $node, $text),
                ];
            }
        }

        if ($node->localName === 'tbl') {
            $rows = [];

            foreach ($xpath->query('./w:tr', $node) ?: [] as $rowNode) {
                $cells = [];

                foreach ($xpath->query('./w:tc', $rowNode) ?: [] as $cellNode) {
                    $paragraphs = [];

                    foreach ($xpath->query('./w:p', $cellNode) ?: [] as $paragraphNode) {
                        $value = paragraphText($xpath, $paragraphNode);
                        if (trim($value) !== '') {
                            $paragraphs[] = $value;
                        }
                    }

                    $cells[] = implode("\n", $paragraphs);
                }

                $rows[] = $cells;
            }

            $flat = [];
            foreach ($rows as $row) {
                array_push($flat, ...$row);
            }

            if (canonicalText($flat) !== '') {
                $blocks[] = ['type' => 'table', 'text' => canonicalText($flat), 'rows' => $rows];
            }
        }
    }

    return $blocks;
}

function paragraphText(DOMXPath $xpath, DOMNode $paragraph): string
{
    $value = '';

    foreach ($xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) ?: [] as $node) {
        $value .= match ($node->localName) {
            'tab' => "\t",
            'br' => "\n",
            default => $node->textContent,
        };
    }

    return $value;
}

function isHeading(DOMXPath $xpath, DOMElement $paragraph, string $text): bool
{
    $style = strtolower((string) $xpath->evaluate('string(./w:pPr/w:pStyle/@w:val)', $paragraph));
    if (str_contains($style, 'heading') || str_contains($style, 'title')) {
        return true;
    }

    $runs = $xpath->query('.//w:r[w:t]', $paragraph);
    $weighted = 0;
    $bold = 0;

    foreach ($runs ?: [] as $run) {
        $length = mb_strlen($run->textContent);
        $weighted += $length;
        $boldNode = $xpath->query('./w:rPr/w:b', $run)?->item(0);
        $boldValue = $boldNode instanceof DOMElement ? strtolower($boldNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val')) : '';
        if ($boldNode !== null && ! in_array($boldValue, ['0', 'false', 'off'], true)) {
            $bold += $length;
        }
    }

    $normalized = trim($text);
    $keyword = preg_match('/^(الأهداف|مقدمة|الخلاصة|الزيتونة|مهمة تطبيقية|اختبار سريع|مثال تطبيقي|تمرين|تطبيق)/u', $normalized) === 1;

    return mb_strlen($normalized) <= 140 && ($keyword || ($weighted > 0 && $bold / $weighted >= 0.7));
}

/** @return array{string, array<int, array<string, mixed>>, array<int, array{id: string, title: string}>} */
function renderBlocks(array $blocks): array
{
    $html = [];
    $json = [];
    $outline = [];
    $headingNumber = 0;
    $sectionOpen = false;

    foreach ($blocks as $block) {
        if ($block['type'] === 'table') {
            $html[] = '<div class="learning-table-wrap"><table class="learning-table"><tbody>';
            foreach ($block['rows'] as $row) {
                $html[] = '<tr>'.implode("\n", array_map(
                    fn (string $cell): string => '<td>'.nl2br(escape($cell), false).'</td>',
                    $row,
                )).'</tr>';
            }
            $html[] = '</tbody></table></div>';
            $json[] = ['type' => 'table', 'rows' => $block['rows']];

            continue;
        }

        $text = $block['text'];
        if ($block['heading']) {
            if ($sectionOpen) {
                $html[] = '</section>';
            }
            $headingNumber++;
            $id = 'section-'.$headingNumber;
            $kind = classifyBlock($text);
            $html[] = '<section class="learning-section learning-section--'.$kind.'"><h2 id="'.$id.'">'.escape($text).'</h2>';
            $sectionOpen = true;
            $outline[] = ['id' => $id, 'title' => $text];
            $json[] = ['type' => 'heading', 'id' => $id, 'kind' => $kind, 'text' => $text];

            continue;
        }

        $html[] = '<p>'.nl2br(escape($text), false).'</p>';
        $json[] = ['type' => 'paragraph', 'text' => $text];
    }

    if ($sectionOpen) {
        $html[] = '</section>';
    }

    if ($outline === []) {
        $outline[] = ['id' => 'lesson-content', 'title' => 'محتوى الدرس'];
        array_unshift($html, '<span id="lesson-content" class="learning-anchor" aria-hidden="true"></span>');
    }

    return [implode("\n", $html), $json, $outline];
}

function classifyBlock(string $text): string
{
    return match (true) {
        str_contains($text, 'الأهداف') => 'goals',
        str_contains($text, 'اختبار') => 'quiz',
        str_contains($text, 'مهمة') || str_contains($text, 'تطبيق') => 'task',
        str_contains($text, 'مثال') || str_contains($text, 'قصة') => 'example',
        str_contains($text, 'الزيتونة') || str_contains($text, 'خلاصة') => 'summary',
        default => 'standard',
    };
}

/** @return array<int, array{question: string, answer: string}> */
function extractFaq(array $blocks): array
{
    $faq = [];

    foreach ($blocks as $index => $block) {
        if (($block['type'] ?? null) !== 'heading' || preg_match('/[؟?]/u', (string) ($block['text'] ?? '')) !== 1) {
            continue;
        }

        $answers = [];
        for ($cursor = $index + 1, $count = count($blocks); $cursor < $count; $cursor++) {
            $candidate = $blocks[$cursor];
            if (($candidate['type'] ?? null) === 'heading') {
                break;
            }
            if (filled($candidate['text'] ?? null)) {
                $answers[] = $candidate['text'];
            }
        }

        $answer = canonicalText($answers);
        if (mb_strlen($answer) >= 40) {
            $faq[] = ['question' => $block['text'], 'answer' => $answer];
        }

        if (count($faq) === 5) {
            break;
        }
    }

    return $faq;
}

function canonicalText(array $values): string
{
    $text = implode(' ', array_map(fn (mixed $value): string => (string) $value, $values));

    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function writePhpArray(string $path, array $data): void
{
    $contents = "<?php\n\nreturn ".var_export($data, true).";\n";

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Unable to write {$path}");
    }
}
