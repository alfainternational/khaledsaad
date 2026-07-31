<?php

return [
    /*
     * يصفان النسخة **المنشورة فعلًا**، ويجب أن يطابقا
     * `public/downloads/release.json` — يحرس ذلك MobileDownloadTest.
     *
     * لا تُرفع هذه القيم إلا بعد بناء APK ونشره وتحديث المانيفست. رفعها
     * قبلها يجعل صفحة التنزيل تعلن نسخة لا وجود لها.
     *
     * نسخة البناء القادم مكانها `mobile/pubspec.yaml`.
     */
    'version' => env('MOBILE_APP_VERSION', '1.0.6'),
    'build' => (int) env('MOBILE_APP_BUILD', 9),

    /*
     * أقل بناء مسموح له باستهلاك api/v1.
     *
     * صفر = البوابة مشحونة وغير مفعّلة. لا تُرفع هذه القيمة إلا بعد شحن نسخة
     * تطبيق ترسل ترويسة X-App-Build ووصولها لمستخدميها؛ رفعها قبل ذلك يمنع
     * الوصول عن كل النسخ المثبَّتة دفعة واحدة لأنها لا ترسل الترويسة.
     */
    'min_supported_build' => (int) env('MOBILE_MIN_SUPPORTED_BUILD', 0),
    'android_package' => 'net.khaledsaad.ksgrowth_mobile',
    'ios_bundle' => 'net.khaledsaad.ksgrowthMobile',
    'apk_path' => public_path('downloads/khaledsaad-growth.apk'),
];
