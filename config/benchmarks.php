<?php

return [
    /*
     * مصدر أرقام المقارنة التي تظهر بجانب الأسئلة الرقمية.
     *
     * curated: جدول مراجَع يدويًا داخل المشروع — يعمل دائمًا وبلا تكلفة.
     * live:    واجهة سوق حيّة تُرجع تكلفة النقرة وحجم البحث الحقيقيين،
     *          ونشتق منها تقديرًا لتكلفة العميل. تُستخدم إن توفر المفتاح فقط.
     */
    'live_enabled' => env('BENCHMARKS_LIVE_ENABLED', false),

    'live' => [
        // dataforseo | serper | keyword_planner
        'driver' => env('BENCHMARKS_LIVE_DRIVER', 'dataforseo'),
        'login' => env('BENCHMARKS_LIVE_LOGIN'),
        'password' => env('BENCHMARKS_LIVE_PASSWORD'),
        'api_key' => env('BENCHMARKS_LIVE_KEY'),
        'base_url' => env('BENCHMARKS_LIVE_BASE_URL', 'https://api.dataforseo.com'),
        'timeout' => (int) env('BENCHMARKS_LIVE_TIMEOUT', 8),
    ],

    /*
     * الرقم الحيّ يُخزَّن ولا يُطلب مع كل فتح صفحة: السوق لا يتغير بالساعة،
     * والاستضافة المشتركة لا تحتمل نداءً خارجيًا داخل كل طلب.
     */
    'cache_days' => (int) env('BENCHMARKS_CACHE_DAYS', 30),

    /*
     * نسب تحويل إرشادية لاشتقاق «تكلفة العميل» من «تكلفة النقرة».
     * تكلفة العميل ≈ تكلفة النقرة ÷ نسبة من ينتهي بالشراء.
     */
    'click_to_customer_rate' => [
        'b2c' => 0.02,
        'marketplace' => 0.025,
        'saas' => 0.015,
        'services' => 0.01,
        'b2b' => 0.005,
        'default' => 0.015,
    ],
];
