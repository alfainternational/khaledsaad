<?php

/**
 * فاحص حالة الأحرف (case) للكلاسات قبل النشر.
 *
 * يكشف الخلل الذي يعمل على ويندوز (نظام ملفات غير حسّاس للحالة) ويفشل على Linux
 * (حسّاس): مرجع كلاس `AICreditService` بينما الملف `AiCreditService.php`.
 *
 * يفحص لكل ملف PHP تحت app/:
 *   1) أن مسار الملف يطابق namespace+class المعرّف داخله (حالة مطابقة تماماً).
 *   2) أن كل `use App\...` يشير إلى ملف موجود بنفس الحالة حرفياً.
 *
 * المقارنة حسّاسة للحالة على كل المنصّات عبر scandir لكل مقطع من المسار.
 * exit 0 = نظيف · exit 1 = توجد تعارضات.
 */

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$psr4 = $composer['autoload']['psr-4'] ?? [];

/** يحوّل FQN إلى مسار نسبي متوقّع عبر خريطة PSR-4، أو null إن لم يكن مُداراً (vendor/global). */
function expectedRelPath(string $fqn, array $psr4): ?string
{
    $fqn = ltrim($fqn, '\\');
    foreach ($psr4 as $prefix => $dir) {
        $prefix = ltrim($prefix, '\\');
        if ($prefix !== '' && str_starts_with($fqn, $prefix)) {
            $rel = substr($fqn, strlen($prefix));

            return rtrim($dir, '/').'/'.str_replace('\\', '/', $rel).'.php';
        }
    }

    return null;
}

/** يتحقق من وجود المسار النسبي بحالة الأحرف الحرفية (حسّاس حتى على ويندوز). */
function existsExact(string $root, string $relPath): bool
{
    $current = rtrim($root, '/\\');
    foreach (explode('/', trim($relPath, '/')) as $seg) {
        $entries = @scandir($current);
        if ($entries === false || ! in_array($seg, $entries, true)) {
            return false;
        }
        $current .= '/'.$seg;
    }

    return true;
}

/** كل ملفات PHP تحت app/. */
function phpFiles(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $out[] = $file->getPathname();
        }
    }

    return $out;
}

$problems = [];
$checkedRefs = 0;

foreach (phpFiles($root.'/app') as $absPath) {
    $code = (string) file_get_contents($absPath);
    $relFile = str_replace('\\', '/', substr($absPath, strlen($root) + 1));

    // (1) تعريف namespace + class/interface/trait/enum
    if (preg_match('/^namespace\s+([^;]+);/m', $code, $ns)
        && preg_match('/^\s*(?:final\s+|abstract\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $cl)) {
        $fqn = trim($ns[1]).'\\'.$cl[1];
        $expected = expectedRelPath($fqn, $psr4);
        if ($expected !== null && ! existsExact($root, $expected)) {
            $problems[] = "تعريف لا يطابق الملف: {$fqn}\n   متوقّع: {$expected}\n   فعلي:   {$relFile}";
        }
    }

    // (2) عبارات use App\... (مع تجاهل function/const والـ alias)
    if (preg_match_all('/^use\s+((?:App)\\\\[A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?\s*;/m', $code, $uses)) {
        foreach ($uses[1] as $used) {
            $expected = expectedRelPath($used, $psr4);
            if ($expected === null) {
                continue;
            }
            $checkedRefs++;
            if (! existsExact($root, $expected)) {
                $problems[] = "مرجع use بحالة خاطئة في {$relFile}:\n   use {$used};\n   لا يوجد ملف مطابق الحالة: {$expected}";
            }
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "✗ فحص حالة الأحرف: ".count($problems)." تعارض (سيفشل على Linux):\n\n");
    foreach ($problems as $p) {
        fwrite(STDERR, ' - '.$p."\n\n");
    }
    exit(1);
}

echo "✓ فحص حالة الأحرف: نظيف ({$checkedRefs} مرجع use مفحوص).\n";
exit(0);
