<?php

namespace App\Domain\AI\Kernel\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\LocalizationSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\OfferConversionSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\SearchVisibilitySpecialist;

/**
 * خدمة «مراجعة الأخصائيين» — تجمع مراجعة الأخصائيين المحليين لمخرَج واحد
 * وتعيدها كلوحات جاهزة للعرض. الأساس الخلفي لبطاقة المراجعة في شاشة المستخدم.
 *
 * المتصل يختار الجوانب المناسبة للسياق (المرحلة/الأداة)، والخدمة تشغّل كل جانب
 * محلياً (بلا مورد خارجي) وتعيد درجة كلّية + لوحة لكل أخصائي. تتدهور بأمان:
 * نص فارغ ⇒ لوحات فارغة بلا خطأ.
 */
class SpecialistReviewService
{
    public const ASPECT_LOCALIZATION = 'localization';

    public const ASPECT_OFFER = 'offer';

    public const ASPECT_SEARCH = 'search';

    public function __construct(
        private readonly LocalizationSpecialist $localization,
        private readonly OfferConversionSpecialist $offer,
        private readonly SearchVisibilitySpecialist $search,
    ) {}

    /**
     * @param  array<int, string>  $aspects  مجموعة من ثوابت ASPECT_*
     * @param  array{title?: string, keyword?: string}  $meta  سياق إضافي (للبحث)
     * @return array{score: int|null, panels: array<int, array{key: string, name: string, score: int, items: array<int, string>}>}
     */
    public function review(string $text, array $aspects, array $meta = []): array
    {
        $text = trim($text);
        $panels = [];

        if ($text === '') {
            return ['score' => null, 'panels' => []];
        }

        if (in_array(self::ASPECT_LOCALIZATION, $aspects, true)) {
            $r = $this->localization->analyze($text);
            $panels[] = [
                'key' => self::ASPECT_LOCALIZATION,
                'name' => 'صياغة عربية',
                'score' => (int) $r['score'],
                'items' => array_map(fn (array $i): string => (string) $i['label'], $r['issues']),
            ];
        }

        if (in_array(self::ASPECT_OFFER, $aspects, true)) {
            $r = $this->offer->analyze($text);
            $items = array_map(fn (array $f): string => (string) $f['hint'], $r['findings']);
            $panels[] = [
                'key' => self::ASPECT_OFFER,
                'name' => 'قوة العرض',
                'score' => (int) $r['score'],
                'items' => $items === [] ? $r['strengths'] : $items,
            ];
        }

        if (in_array(self::ASPECT_SEARCH, $aspects, true)) {
            $r = $this->search->analyze(
                (string) ($meta['title'] ?? ''),
                $text,
                (string) ($meta['keyword'] ?? ''),
            );
            $panels[] = [
                'key' => self::ASPECT_SEARCH,
                'name' => 'الظهور في البحث',
                'score' => (int) $r['score'],
                'items' => array_map(fn (array $f): string => (string) $f['hint'], $r['findings']),
            ];
        }

        return [
            'score' => $this->overall($panels),
            'panels' => $panels,
        ];
    }

    /**
     * الدرجة الكلّية = متوسّط درجات اللوحات (مقرّب). null لو لا لوحات.
     *
     * @param  array<int, array{score: int}>  $panels
     */
    private function overall(array $panels): ?int
    {
        if ($panels === []) {
            return null;
        }

        $sum = array_sum(array_map(fn (array $p): int => (int) $p['score'], $panels));

        return (int) round($sum / count($panels));
    }
}
