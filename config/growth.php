<?php

/*
 * محرك النمو المستمر: التقرير الحي، النبض الأسبوعي، حزمة الظهور للآلات،
 * والجمهور الاصطناعي. المفاتيح قابلة للضبط من لوحة الإدارة (SettingsConfig).
 */
return [

    // الفحص اليومي للتقارير الحيّة.
    'watch_enabled' => env('GROWTH_WATCH_ENABLED', true),

    // النبض الأسبوعي.
    'pulse_enabled' => env('GROWTH_PULSE_ENABLED', true),

    // فرق الدرجة (بالنقاط) الذي يستحق تنبيه «درجتك تغيّرت».
    'score_drift_threshold' => env('GROWTH_SCORE_DRIFT', 5),

    // عمر التقرير (بالأيام) الذي يُعد بعده قديمًا في النبض.
    'stale_days' => env('GROWTH_STALE_DAYS', 45),

];
