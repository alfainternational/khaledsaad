<?php

namespace App\Contracts;

use App\Modules\Competitors\AdLibraries\AdSnapshot;

/**
 * مصدر سحب مكتبات الإعلانات لمنافس على منصة واحدة.
 *
 * كل مزوّد خارجي خلف interface (§٨)، والسحب هشّ بطبعه (§١٠). المزوّد لا يرمي
 * عند الفشل ولا يختلق إعلانات: يعيد `AdSnapshot` بحالة `broke` أو
 * `unavailable` وملاحظة تغطية. اختلاق إعلان أسوأ من إعلان غائب (§٤.١).
 */
interface AdLibraryProvider
{
    /**
     * هل المزوّد مضبوط وجاهز؟ غير المضبوط لا يُستدعى ولا يُحجز له من السقف.
     */
    public function isAvailable(): bool;

    /**
     * @param  string  $platform  مفتاح المنصة (meta/tiktok/google…)
     * @param  string  $advertiser  اسم المنافس أو نطاقه كما يُبحث به
     */
    public function fetch(string $platform, string $advertiser): AdSnapshot;

    /**
     * المنصات التي يعرف هذا المزوّد سحبها.
     *
     * @return array<int, string>
     */
    public function supportedPlatforms(): array;
}
