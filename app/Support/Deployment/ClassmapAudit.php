<?php

namespace App\Support\Deployment;

/**
 * كشف الإدخالات القديمة في خريطة أصناف Composer.
 *
 * منطق واحد يستعمله الفاحص (`platform:preflight`) والمُصلح
 * (`deploy/prune-classmap.php`). أول نسخة كان لكلٍّ منهما منطقه، فاختلفا:
 * الفاحص كان يمسح الملف كله فيعدّ جذور PSR-4 — وهي مجلدات لا ملفات — فيعلن
 * ١١١ عطلًا في خريطة سليمة تمامًا.
 *
 * الدرس مكتوب هنا لأنه يتكرر: قاعدة تُطبَّق في مكانين تتفرّع إلى قاعدتين.
 */
class ClassmapAudit
{
    /**
     * حدود كتلة `$classMap` داخل `autoload_static.php`.
     *
     * الحصر ضروري: الملف يحمل كذلك `$prefixDirsPsr4` و`$prefixLengthsPsr4`،
     * وقيمهما مجلدات ومقاطع نصية لا مسارات ملفات.
     */
    private const MAP_OPEN = 'public static $classMap = array (';

    /**
     * الأسطر التي تشير إلى ملف غير موجود داخل كتلة الأصناف.
     *
     * @return array<int, array{line: int, path: string}>
     */
    public function staleInStatic(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $base = dirname($file);
        $lines = file($file) ?: [];
        $inMap = false;
        $stale = [];

        foreach ($lines as $index => $line) {
            if (! $inMap) {
                $inMap = str_contains($line, self::MAP_OPEN);

                continue;
            }

            if (preg_match('/^\s*\);\s*$/', $line)) {
                break;
            }

            $path = $this->resolve($line, $base);

            if ($path !== null && ! is_file($path)) {
                $stale[] = ['line' => $index, 'path' => $path];
            }
        }

        return $stale;
    }

    /**
     * الإدخالات القديمة في `autoload_classmap.php` (التوليد غير المُحسَّن).
     *
     * @return array<string, string> صنف => مسار
     */
    public function staleInClassmap(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $map = require $file;

        if (! is_array($map)) {
            return [];
        }

        return array_filter($map, static fn ($path) => ! is_string($path) || ! is_file($path));
    }

    /**
     * يفكّ تعبير المسار المولَّد: `__DIR__ . '/../..' . '/app/X.php'`.
     *
     * يعيد null إن تعذّر الفهم، فلا يُحسب السطر عطلًا لمجرد أن شكله مفاجئ.
     */
    private function resolve(string $line, string $base): ?string
    {
        $arrow = strpos($line, '=>');

        if ($arrow === false) {
            return null;
        }

        $expression = trim(rtrim(substr($line, $arrow + 2), ",\n\r "));

        if (! preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $expression, $matches)) {
            return null;
        }

        $path = '';
        foreach ($matches[1] as $segment) {
            $path .= stripcslashes($segment);
        }

        if ($path === '') {
            return null;
        }

        return str_starts_with($expression, '__DIR__') ? $base.$path : $path;
    }
}
