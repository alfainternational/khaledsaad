<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\TemplateGap;
use Illuminate\View\View;

/**
 * ما ينقص النظام: قوالب غائبة، وبيانات لا يعطيها أصحاب الأنشطة.
 *
 * **سبب وجودها:** `template_gaps` كان جدولًا للكتابة فقط. `TemplateResolver`
 * يسجّل فيه كل مرة يعجز فيها عن بناء ورقة، ولا يقرؤه أحد: لا شاشة، ولا أمر،
 * ولا تقرير. فالنظام كان يعرف بالضبط أي قالب ينقصه وكم مرة احتاجه مستخدم،
 * وتبقى المعرفة في جدول لا يفتحه إنسان — بينما يرى المستخدم «هذه التوصية
 * غير جاهزة للتنفيذ» ولا يعرف أحد كم مرة حدث ذلك.
 *
 * والشاشة تجمع النقصين لأنهما يُقرآن معًا: نقصٌ عندنا يُصلَح بتأليف قالب،
 * ونقصٌ عند المستخدم يُصلَح بسؤال أوضح أو حقل يُضاف إلى الاستقبال. وتكرار
 * الحقل نفسه عبر عشرات التقارير يقول إن السؤال نفسه هو المشكلة لا أصحابها.
 */
class AdminReportingGapController extends Controller
{
    public function index(): View
    {
        return view('admin.reporting.gaps', [
            'templateGaps' => TemplateGap::with('objective')
                ->orderByDesc('occurrences')
                ->limit(100)
                ->get(),
            'fieldGaps' => $this->fieldGaps(),
        ]);
    }

    /**
     * الحقول الأكثر غيابًا عبر التقارير، مرتبةً بعدد مرات إعلانها.
     *
     * الحساب في PHP لا في SQL عمدًا: `declared_gaps` عمود JSON، واستخراج
     * عناصر مصفوفة منه يختلف بين MySQL وSQLite — والاختبارات تعمل على
     * الثانية. استعلامٌ يمرّ في الإنتاج ويسقط في الاختبار أسوأ من حلقة على
     * بضع مئات من الصفوف.
     *
     * @return array<int, array{key: string, label: string, source: string, reports: int, answered: int}>
     */
    private function fieldGaps(): array
    {
        $tally = [];

        Report::query()
            ->whereNotNull('declared_gaps')
            ->select(['id', 'declared_gaps'])
            ->orderByDesc('id')
            ->limit(2000)
            ->chunk(200, function ($reports) use (&$tally): void {
                foreach ($reports as $report) {
                    foreach ($report->declared_gaps ?? [] as $gap) {
                        if (! is_array($gap) || ($gap['key'] ?? '') === '') {
                            continue;
                        }

                        $key = (string) $gap['key'];
                        $tally[$key] ??= [
                            'key' => $key,
                            'label' => (string) ($gap['label'] ?? $key),
                            'source' => (string) ($gap['source'] ?? '—'),
                            'reports' => 0,
                            'answered' => 0,
                        ];

                        $tally[$key]['reports']++;

                        if (($gap['answered_at'] ?? null) !== null) {
                            $tally[$key]['answered']++;
                        }
                    }
                }
            });

        usort($tally, fn (array $a, array $b): int => $b['reports'] <=> $a['reports']);

        return array_slice(array_values($tally), 0, 100);
    }
}
