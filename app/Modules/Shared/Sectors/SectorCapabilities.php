<?php

namespace App\Modules\Shared\Sectors;

use App\Models\Tool;
use App\Models\ToolField;
use App\Modules\AiReadiness\QuestionBank;
use App\Support\Kpis\KpiTemplates;

/**
 * برهان التخصص، مقروءًا من المحرّك لا مكتوبًا بجانبه.
 *
 * **لماذا يُحسب ولا يُكتب:** صفحة تقول «٤٠ سؤالًا خاصًّا بقطاعك» بينما البنك
 * فيه ١١ هي وعدٌ يخالف §٤.٢ — رقمٌ بلا أساس مرصود. وحين تُقرأ الأرقام من
 * تعريفات الأدوات وقوالب المؤشرات وبنك الأسئلة نفسها، يستحيل أن ينحرف الوعد
 * عن المنتج: تحذف سؤالًا فينقص العدّاد في الصفحة من تلقائه.
 *
 * كل ما هنا `measured` بالمعنى الحرفي: مصدره جداولنا وملفاتنا لا تقدير.
 */
final class SectorCapabilities
{
    /**
     * @return array<string, mixed>
     */
    public function for(string $sector): array
    {
        $profile = SectorProfile::for($sector);

        return [
            'sector' => $sector,
            'label' => Sector::label($sector),
            'profile' => $profile,

            /*
             * عدد الأسئلة القطاعية والأدوات التي تحملها — من الحقول المبذورة
             * لا من ملفات التعريف: ما بُذر هو ما سيراه المستخدم فعلًا، وملفٌ
             * عُدِّل ولم يُسكّ إصداره وعدٌ لم يصل.
             */
            'questions' => $this->questionsIn($sector),

            // المؤشرات التي تتصدّر لوحته لأنه اختار هذا القطاع.
            'kpis' => $this->kpisIn($sector),

            // أسئلة المشتري التي نقيس بها ظهوره في إجابات النماذج.
            'buyer_questions' => $profile === null ? [] : QuestionBank::samplesFor(
                $sector,
                $profile['sample_category'],
                $profile['sample_city'],
            ),

            // ما يفحصه التدقيق التقني في هذا القطاع دون غيره.
            'schema' => $this->schemaFor($sector),
        ];
    }

    /**
     * @return array{count: int, tools: int, samples: array<int, string>}
     */
    private function questionsIn(string $sector): array
    {
        /*
         * الإصدار الفعّال وحده.
         *
         * قراءة كل صفوف `tool_fields` تعدّ السؤال مرة لكل إصدار سابق: بعد
         * سكّ v3 صارت أسئلة التجارة الإلكترونية «٢٤» بينما الظاهر للمستخدم
         * ١٠، و«١٤ تشخيصًا» بينما الأدوات إحدى عشرة — لأن العدّ كان على
         * `tool_version_id` لا على الأداة. الرقم الذي يعد المستخدمَ بشيء
         * يجب أن يُحصى من حيث يراه: الإصدار الذي سيُشغّله.
         */
        $activeVersionIds = Tool::query()
            ->with('currentVersion')
            ->get()
            ->map(fn (Tool $tool) => $tool->currentVersion?->id)
            ->filter()
            ->all();

        $fields = ToolField::query()
            ->whereIn('tool_version_id', $activeVersionIds)
            ->whereNotNull('visible_when')
            ->get()
            ->filter(function (ToolField $field) use ($sector) {
                $declared = $field->visible_when['project.sector'] ?? null;

                return is_array($declared) ? in_array($sector, $declared, true) : $declared === $sector;
            });

        return [
            'count' => $fields->count(),
            'tools' => $fields->pluck('tool_version_id')->unique()->count(),
            // نماذج حيّة من الأسئلة نفسها: الوعد يُرى لا يُوصف.
            'samples' => $fields->take(4)->pluck('label')->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpisIn(string $sector): array
    {
        $groups = KpiTemplates::catalog($sector);
        $first = $groups[0] ?? null;

        return ($first['sector'] ?? null) === $sector ? $first['items'] : [];
    }

    /**
     * @return array{types: array<int, string>, label: string}
     */
    private function schemaFor(string $sector): array
    {
        return match ($sector) {
            Sector::EDUCATION => ['types' => ['Course', 'EducationalOrganization'], 'label' => 'البرامج الدراسية'],
            Sector::REAL_ESTATE => ['types' => ['RealEstateListing', 'RealEstateAgent'], 'label' => 'الوحدات العقارية'],
            default => ['types' => ['Product', 'Store'], 'label' => 'المنتجات'],
        };
    }
}
