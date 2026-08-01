<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Sectors\Sector;
use App\Modules\Shared\Sectors\SectorCapabilities;
use Illuminate\View\View;

/**
 * صفحة القطاع: أين يُثبَت التخصص بدل أن يُدَّعى.
 *
 * كل رقم ونصّ فيها يأتي من `SectorCapabilities` أي من الأدوات المبذورة
 * وقوالب المؤشرات وبنك الأسئلة — فلا يمكن أن تعد الصفحة بما لا يفعله
 * المحرّك، ولا أن تتخلّف عن قدرة أُضيفت.
 */
class SectorLandingController extends Controller
{
    public function __construct(private readonly SectorCapabilities $capabilities) {}

    /**
     * القطاعات الثلاثة جنبًا إلى جنب.
     *
     * **سبب وجودها:** رابط «قطاعاتنا» كان يذهب إلى قطاع واحد، فيقرأ الزائر
     * أننا متخصصون في التعليم ويظن الاثنين الآخرين حاشية. التخصص الثلاثي
     * يُعرض ثلاثيًّا أو لا يُصدَّق.
     */
    public function index(): View
    {
        return view('site.sectors.index', [
            'brand' => config('brand'),
            'sectors' => array_map(
                fn (string $sector) => $this->capabilities->for($sector),
                Sector::SPECIALIZED,
            ),
        ]);
    }

    public function show(string $sector): View
    {
        // المسار محصور أصلًا بالقطاعات المتخصصة؛ الحارس هنا لئلا يعتمد
        // الأمان على قيد مسار قد يُوسَّع لاحقًا بلا انتباه.
        abort_unless(Sector::isSpecialized($sector), 404);

        return view('site.sectors.show', [
            'brand' => config('brand'),
            'capabilities' => $this->capabilities->for($sector),
            'otherSectors' => array_values(array_diff(Sector::SPECIALIZED, [$sector])),
        ]);
    }
}
