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

// التقرير الحي: فحص حتمي يومي بلا تكلفة نموذج — بعد تحديثات السوق الليلية.
Schedule::command('growth:watch')->dailyAt('04:10')->withoutOverlapping();

// نبض الأسبوع: صباح الاثنين، بعد أن تكون أرقام السوق والمنافسون قد تحدّثوا.
Schedule::command('growth:pulse')->weeklyOn(1, '07:30')->withoutOverlapping();
