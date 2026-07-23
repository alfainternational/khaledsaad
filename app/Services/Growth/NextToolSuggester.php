<?php

namespace App\Services\Growth;

use App\Models\Project;
use App\Models\Tool;

/**
 * بذرة التنسيق بين الأدوات: مخرج أداة يقود إلى الأداة التالية تلقائيًا،
 * فلا يقف المستخدم أمام إحدى عشرة أداة يسأل «والآن ماذا؟».
 *
 * الاقتراح حتمي: مسار رحلة ثابت + تعزيز من فئات نتائج آخر تقرير،
 * مع استبعاد ما شغّله المستخدم فعلًا.
 */
class NextToolSuggester
{
    /**
     * ترتيب الرحلة الطبيعي: شخّص، وضّح هويتك، اعرف جمهورك، ابنِ عرضك،
     * اختر قنواتك، أنتج محتواك، افحص قمعك، ثم توسّع.
     *
     * @var array<int, string>
     */
    private const JOURNEY = [
        'marketing-score', 'brand-clarity', 'audience-map', 'offer-builder',
        'channel-fit', 'content-engine', 'funnel-audit', 'seo-compass',
        'campaign-planner', 'competitor-lens', 'agency-brief',
    ];

    /**
     * كلمات في فئات النتائج تعزز أداة بعينها: التقرير الذي يكشف ضعف
     * الجمهور يقود إلى خريطة الجمهور لا إلى الترتيب الافتراضي.
     *
     * @var array<string, array<int, string>>
     */
    private const CATEGORY_BOOST = [
        'audience-map' => ['جمهور', 'شريحة', 'استهداف', 'عملاء'],
        'brand-clarity' => ['هوية', 'علامة', 'تموضع', 'رسالة'],
        'offer-builder' => ['عرض', 'سعر', 'تسعير', 'قيمة'],
        'channel-fit' => ['قناة', 'قنوات', 'إعلان'],
        'content-engine' => ['محتوى', 'نشر', 'كتابة'],
        'funnel-audit' => ['قمع', 'تحويل', 'مبيعات'],
        'seo-compass' => ['بحث', 'ظهور', 'موقع', 'زيارات'],
        'competitor-lens' => ['منافس', 'منافسة'],
        'campaign-planner' => ['حملة', 'خطة', 'ميزانية'],
    ];

    /**
     * @return array{tool: Tool, reason: string}|null
     */
    public function suggest(Project $project): ?array
    {
        $usedKeys = $project->reports()
            ->with('toolRun.toolVersion.tool')
            ->get()
            ->map(fn ($report) => $report->toolRun?->toolVersion?->tool?->key)
            ->filter()
            ->unique()
            ->all();

        $runnable = Tool::runnable()->get()->keyBy('key');

        // التعزيز من آخر تقرير: فئة النتيجة تدل على مكان الوجع الفعلي.
        $boosted = $this->boostedKey($project, $usedKeys, $runnable->keys()->all());

        if ($boosted !== null) {
            return [
                'tool' => $runnable[$boosted],
                'reason' => 'آخر تقرير كشف أن هذه أكثر منطقة تحتاج ترتيبًا عندك.',
            ];
        }

        foreach (self::JOURNEY as $key) {
            if (! in_array($key, $usedKeys, true) && $runnable->has($key)) {
                return [
                    'tool' => $runnable[$key],
                    'reason' => $usedKeys === []
                        ? 'نقطة البداية الطبيعية: قياس شامل قبل أي خطوة.'
                        : 'الخطوة التالية في رحلة ترتيب تسويقك.',
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $usedKeys
     * @param  array<int, string>  $runnableKeys
     */
    private function boostedKey(Project $project, array $usedKeys, array $runnableKeys): ?string
    {
        $latest = $project->reports()->latest('created_at')->with('findings')->first();

        if ($latest === null) {
            return null;
        }

        $haystack = $latest->findings
            ->map(fn ($finding) => $finding->category.' '.$finding->title)
            ->implode(' ');

        foreach (self::CATEGORY_BOOST as $toolKey => $keywords) {
            if (in_array($toolKey, $usedKeys, true) || ! in_array($toolKey, $runnableKeys, true)) {
                continue;
            }

            foreach ($keywords as $keyword) {
                if (mb_strpos($haystack, $keyword) !== false) {
                    return $toolKey;
                }
            }
        }

        return null;
    }
}
