<?php

namespace App\Modules\Reporting\Gaps;

use App\Models\ToolRun;
use App\Modules\Intake\Fields\FieldDirectory;

/**
 * الفجوات المعلنة في تقرير: ما ينقص النظام عن هذا النشاط، ومفتاح كل نقص.
 *
 * **المشكلة التي يحلّها:** كان التقرير يقول «ناقص نعرفه عنك: جمهورك» ثم
 * ينتهي السطر. لا زر، ولا رابط، ولا شاشة. والسبب أن النقص كان يصل من
 * النموذج نصًّا حرًّا بلا مفتاح — فحتى لو أردنا أن نفتح له بابًا، لا نعرف
 * أي سؤال يفتح. §٤.٣ تقول إن الفجوة تُعلن ولا تُخفى، وكنا نعلنها ونقف.
 *
 * ─── لماذا مصدران، والحتميّ أولًا ───
 *
 * الحقل الذي تركه صاحب النشاط فارغًا **معلوم** بلا نموذج: نقارن حقول
 * الأداة بإجاباته فنعرف. هذا مصدر لا يخطئ ولا يكلّف استعلامًا ولا يتأثر
 * بمزاج نموذج (§٤.٢).
 *
 * ويبقى للنموذج دور لا يؤديه العدّ: أن يقول إن الجواب **موجود لكنه لا
 * يكفي** — «الجميع» جوابٌ مكتوب وفجوةٌ حقيقية. فما يضيفه يُقبل بشرط أن
 * يحمل مفتاحًا نعرفه؛ وما لا مفتاح له يبقى ملاحظة نصّية بلا زر، لأن زرًّا
 * يفتح شاشة فارغة أسوأ من غياب الزر.
 */
final class DeclaredGaps
{
    public function __construct(private readonly FieldDirectory $fields) {}

    /**
     * @param  array<string, mixed>|null  $gaps  مخرَج مرحلة النواقص كما عاد من النموذج
     * @return array<int, array{key: string, label: string, help: ?string, source: string, why: ?string, origin: string}>
     */
    public function forRun(ToolRun $run, ?array $gaps): array
    {
        $declared = [];

        foreach ($this->unanswered($run) as $key) {
            $described = $this->fields->describe($key);

            if ($described !== null) {
                $declared[$key] = [...$described, 'why' => null, 'origin' => 'unanswered'];
            }
        }

        foreach ($gaps['missing'] ?? [] as $missing) {
            if (! is_array($missing)) {
                continue;
            }

            $key = trim((string) ($missing['field_key'] ?? ''));
            $described = $key === '' ? null : $this->fields->describe($key);

            if ($described === null) {
                continue;
            }

            $why = trim((string) ($missing['why_it_matters'] ?? ''));

            /*
             * تفسير النموذج يُضاف ولو كان الحقل مرصودًا فارغًا أصلًا: العدّ
             * يعرف **أنه** ناقص، والنموذج يعرف **لماذا يهمّ هنا**. وحين
             * يتعارضان في شيء فالمرصود هو الذي يبقى.
             */
            $declared[$key] = [
                ...$described,
                'why' => $why === '' ? ($declared[$key]['why'] ?? null) : $why,
                'origin' => $declared[$key]['origin'] ?? 'weak',
            ];
        }

        return array_values($declared);
    }

    /**
     * مفاتيح حقول الأداة التي لم يُكتب لها جواب في هذا التشغيل.
     *
     * الحقول المخفية بشرط `visible_when` تُستثنى: سؤالٌ لم يُعرض على صاحب
     * النشاط أصلًا ليس نقصًا منه. اتّهامه بترك ما لم يُسأل عنه يجعل قائمة
     * النواقص غير قابلة للتصديق، فتُهمَل كلها.
     *
     * @return array<int, string>
     */
    private function unanswered(ToolRun $run): array
    {
        $run->loadMissing(['toolVersion.fields', 'answers']);
        $answers = $run->answerMap();

        return $run->toolVersion->fields
            ->filter(fn ($field) => empty($field->visible_when))
            ->reject(function ($field) use ($answers): bool {
                $value = $answers[$field->key]['value'] ?? $answers[$field->key] ?? null;

                return $value !== null && $value !== '' && $value !== [];
            })
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->values()
            ->all();
    }
}
