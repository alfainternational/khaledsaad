<?php

namespace App\Modules\Competitors\AdLibraries;

use App\Contracts\AdLibraryProvider;

/**
 * المزوّد الافتراضي حين لا يُضبط ساحبٌ حيّ — وهو الحالة الأصدق اليوم.
 *
 * السحب من مكتبات ميتا وتيك توك وجوجل هشّ وقانونيًّا رماديّ (§١٠)، فلا يُشحن
 * ساحبٌ يختلق إعلانات أو يتكسّر صامتًا. هذا المزوّد يعلن الغياب صراحةً: لكل
 * منصة `unavailable` بملاحظة تقول إن السحب لم يُفعَّل بعد، لا صفر إعلانات
 * يُقرأ «لا يُعلن منافسك» (§٤.٣).
 *
 * يُستبدل ببناء ساحب حقيقي خلف الـinterface نفسه حين يُعتمد مزوّد سحب —
 * تمامًا كما يُضبط مزوّد اكتشاف المنافسين من لوحة الآدمن.
 */
class UnavailableAdLibraryProvider implements AdLibraryProvider
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function fetch(string $platform, string $advertiser): AdSnapshot
    {
        return AdSnapshot::unavailable(
            $platform,
            'لم يُفعَّل سحب مكتبات الإعلانات بعد. الروابط الرسمية متاحة للفحص اليدوي.',
        );
    }

    /**
     * @return array<int, string>
     */
    public function supportedPlatforms(): array
    {
        return [];
    }
}
