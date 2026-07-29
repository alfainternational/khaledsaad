<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// أرقام السوق تتغير بالأسابيع لا بالساعات: تحديث أسبوعي يكفي ولا يستنزف المزوّد.
Schedule::command('benchmarks:refresh')->weeklyOn(1, '03:00')->withoutOverlapping();

// اكتشاف مرشّحي المنافسين الإقليميين: أسبوعيًا، مؤجَّلًا عن نداء أرقام السوق.
Schedule::command('competitors:discover')->weeklyOn(1, '03:30')->withoutOverlapping();

/*
 * إعادة تدقيق المواقع: أسبوعيًّا قبل تقييد النقطة، فتُحسب الدرجة على قياس
 * هذا الأسبوع لا على قياس شهر مضى. بلا هذا يتجمّد المحور السابع على أول
 * فحص يدوي، فلا يتغيّر ولا يُنتج تنبيهًا.
 */
Schedule::command('readiness:refresh')->weeklyOn(1, '02:30')->withoutOverlapping();

/*
 * السلسلة الزمنية لدرجة النضج: نقطة كل سبعة أيام لكل نشاط مقيس.
 *
 * قبل الفحص الحي لا بعده: الفحص يقارن بما قُيِّد، فتقييدٌ متأخر يجعل أول
 * مقارنة تتم على نقطة الأمس لا على نقطة الأسبوع الماضي.
 *
 * يومي رغم أن الفاصل أسبوعي: `isDueForPoint` هو من يقرر، فيلتقط الأنشطة
 * الجديدة في يومها بدل أن تنتظر موعدًا ثابتًا.
 */
Schedule::command('diagnosis:record')->dailyAt('04:00')->withoutOverlapping();

// التقرير الحي: فحص حتمي يومي بلا تكلفة نموذج — بعد تحديثات السوق الليلية.
Schedule::command('growth:watch')->dailyAt('04:10')->withoutOverlapping();

// نبض الأسبوع: صباح الاثنين، بعد أن تكون أرقام السوق والمنافسون قد تحدّثوا.
Schedule::command('growth:pulse')->weeklyOn(1, '07:30')->withoutOverlapping();

// إكمال الدفعات التي أغلق العميل متصفحها قبل العودة، دون لمس التحويل اليدوي.
Schedule::command('payments:reconcile --minutes=15 --limit=100')->everyTenMinutes()->withoutOverlapping();
