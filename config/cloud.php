<?php

/*
|--------------------------------------------------------------------------
| تكامل HTTP مع خدمة خارجية (إنتاجي)
|--------------------------------------------------------------------------
|
| المجلد المحلي D:\xampp\htdocs\cloud ليس Laravel — تطبيق TypeScript/Bun. لا يُستورد
| كحزمة PHP. استخدم CloudIntegrationService من التطبيق (يتحقق من الباقة والـ feature flag).
|
*/

return [

    'enabled' => env('CLOUD_INTEGRATION_ENABLED', false),

    'base_url' => env('CLOUD_BASE_URL'),

    'token' => env('CLOUD_API_TOKEN'),

    'timeout' => (float) env('CLOUD_TIMEOUT', 10),

    'connect_timeout' => (float) env('CLOUD_CONNECT_TIMEOUT', 5),

    /*
    | إعادة المحاولة للأعطال الشبكية واستجابات 5xx (بعد الاستجابة الأولى).
    */
    'max_attempts' => max(1, (int) env('CLOUD_HTTP_MAX_ATTEMPTS', 3)),

    'retry_delay_ms' => max(0, (int) env('CLOUD_RETRY_DELAY_MS', 200)),

    /*
    | حد الطلبات الصادرة لكل workspace (دقيقة) — يمنع الإفراط عند أخطاء في العميل.
    */
    'rate_limit_per_minute' => max(1, (int) env('CLOUD_RATE_LIMIT_PER_MINUTE', 60)),

    'log_channel' => env('CLOUD_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),

    /*
    | إظهار جسم الاستجابة في الاستثناءات (يُفضّل false في الإنتاج).
    */
    'expose_error_detail' => env('CLOUD_EXPOSE_ERROR_DETAIL', false),

    'feature_flag_key' => env('CLOUD_FEATURE_FLAG_KEY', 'integrations.cloud_http'),

    'entitlement_key' => env('CLOUD_ENTITLEMENT_KEY', 'integrations.cloud_http'),

    'policy' => [
        'enforce_feature_flag' => env('CLOUD_ENFORCE_FEATURE_FLAG', true),
        'enforce_entitlement' => env('CLOUD_ENFORCE_ENTITLEMENT', true),
    ],

];
