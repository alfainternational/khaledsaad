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
 * سحب مكتبات إعلانات المنافسين: أسبوعيًّا بعد اكتشافهم، فيُسحب على القائمة
 * المؤكَّدة. خامل بلا مزوّد سحب مضبوط — لا يكتب لقطات وهمية (§١٠).
 */
Schedule::command('competitors:scan-ads')->weeklyOn(1, '04:00')->withoutOverlapping();

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

// تنبيهات المهام: تذكير قبل الموعد وإشعار عند التأخر. صباحًا لا فجرًا —
// تنبيه بمهمة يصل الساعة الرابعة يُقرأ بعد أن يكون اليوم قد بدأ بغيرها.
Schedule::command('tasks:remind')->dailyAt('08:00')->withoutOverlapping();

// نبض الأسبوع: صباح الاثنين، بعد أن تكون أرقام السوق والمنافسون قد تحدّثوا.
Schedule::command('growth:pulse')->weeklyOn(1, '07:30')->withoutOverlapping();

/*
 * تجميع إحصاءات الزوّار: يوميًّا فجرًا على آخر يومين.
 *
 * يومان لا يوم: الجلسة التي تبدأ ٢٣:٥٥ تستمر بعد منتصف الليل، وتجميع
 * الأمس وحده يلتقطها ناقصة. وإعادة الكتابة بـ`updateOrCreate` تجعل
 * التداخل تصحيحًا لا مضاعفة.
 */
Schedule::command('insights:rollup --days=2')->dailyAt('02:00')->withoutOverlapping();

// تقليم الصفوف الخام بعد مدة الاحتفاظ. يجمّع قبل أن يحذف بحكم الأمر نفسه.
Schedule::command('insights:prune')->weeklyOn(2, '03:15')->withoutOverlapping();

// إكمال الدفعات التي أغلق العميل متصفحها قبل العودة، دون لمس التحويل اليدوي.
Schedule::command('payments:reconcile --minutes=15 --limit=100')->everyTenMinutes()->withoutOverlapping();

/*
 * تغييرات الخطة المؤجَّلة إلى نهاية الفترة.
 *
 * كل ساعة لا يوميًّا: الفترة تنتهي بطابع زمني لا بيوم، وفحصٌ يومي يترك
 * العميل ساعاتٍ على باقة انتهت — أو ينتظر ترقيته دون سبب ظاهر له.
 */
Schedule::command('subscriptions:apply-scheduled')->hourly()->withoutOverlapping();

/*
 * قدرة مزوّدات الذكاء: كل عشر دقائق.
 *
 * العطل الذي وقع (نفاد الاشتراك) كان يجب أن يصل إلى المشغّل قبل أن يصل
 * إلى مستخدم — وقد وصل إلى مستخدم أولًا لأنه لم يكن أحد يسأل. عشر دقائق
 * لأن نافذة الاكتشاف يجب أن تكون أقصر من صبر مستخدم يجيب عن أسئلة.
 *
 * `--probe` يرسل نداء رمزٍ واحد: تكلفته لا تُذكر، ويكشف نفاد الحصة
 * والمفتاح الباطل قبل أن يكشفهما تشغيلٌ حقيقي.
 */
Schedule::command('ai:watch-capacity --probe')->everyTenMinutes()->withoutOverlapping();

/*
 * إعادة ما أُجّل لعطلٍ لدينا: كل خمس دقائق.
 *
 * الأمر يفحص القدرة أولًا فلا يعيد على مزوّد ما زال ساقطًا. وبدون هذا
 * الجدول تبقى `awaiting_capacity` كلمةً ألطف من «فشل» لا وعدًا مُنفَّذًا.
 */
Schedule::command('runs:resume')->everyFiveMinutes()->withoutOverlapping();
