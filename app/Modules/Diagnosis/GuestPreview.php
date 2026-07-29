<?php

namespace App\Modules\Diagnosis;

use App\Models\ToolVersion;

/**
 * مخرج المستوى ٠: الدرجة + أعلى ثلاث فجوات **بالاسم دون الحل**.
 *
 * الحدّ هنا قرار إيراد لا ذوق تصميم (§٦ و§٢ بند ١٥). الزائر يجب أن يخرج
 * عارفًا **أين** المشكلة وغير عارف **كيف** تُحلّ: الأول يخلق الفجوة المعرفية،
 * والثاني يقفلها فلا يبقى سبب للاشتراك. أي زيادة على هذا تقتل التحويل.
 *
 * ولذلك يُجرَّد البند هنا من نصّ العلاج صراحةً بدل إخفائه في الواجهة: ما لا
 * يُرسَل لا يُسرَّب بتغيير قالب.
 *
 * حتمي بالكامل ولا يمسّ الدماغ: الزائر بلا مشروع، فلا حقائق له ولا تاريخ.
 */
class GuestPreview
{
    /** ما يراه الزائر من فجوات. ثلاث بالضبط (§٨). */
    public const TEASER_LIMIT = 3;

    public function __construct(private readonly DeterministicScorer $scorer) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>|null  $activeKeys
     * @return array<string, mixed>
     */
    public function build(ToolVersion $version, array $answers, ?array $activeKeys = null): array
    {
        $result = $this->scorer->score($version, $answers, $activeKeys);

        return [
            'score' => $result['score'],
            'band' => $result['band'],

            /*
             * أساس الرقم يُعرض معه دائمًا (§١٣): «٤٨ من ٩ بنود» تُقرأ، و«٤٨»
             * وحدها تُصدَّق أو تُرفض بلا معنى.
             */
            'basis_count' => count($result['breakdown']),
            'gaps' => $this->gaps($result['breakdown']),
        ];
    }

    /**
     * أضعف البنود بالاسم وحده.
     *
     * البند المكتمل ليس فجوة، ولذلك يُستبعد قبل الترتيب لا بعده: لو أخذنا
     * أضعف ثلاثة مطلقًا لعرضنا «فجوات» على من لا فجوة لديه.
     *
     * @param  array<int, array<string, mixed>>  $breakdown
     * @return array<int, array<string, mixed>>
     */
    private function gaps(array $breakdown): array
    {
        $weak = array_values(array_filter(
            $breakdown,
            static fn (array $item) => (float) $item['factor'] < 1.0,
        ));

        usort($weak, static function (array $a, array $b): int {
            // الأثر أولًا: بند ناقص بوزن ١٨ يسبق بندًا غائبًا بوزن ٤.
            $lost = ((float) $b['weight']) * (1 - (float) $b['factor'])
                <=> ((float) $a['weight']) * (1 - (float) $a['factor']);

            return $lost !== 0 ? $lost : ($a['label'] <=> $b['label']);
        });

        return array_map(
            static fn (array $item) => ['label' => $item['label']],
            array_slice($weak, 0, self::TEASER_LIMIT),
        );
    }
}
