<?php

return [
    'default' => env('AI_PROVIDER', 'deepseek'),

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 60),

        /*
         * ثلاث فئات كما في وثيقة المنتج. المرحلة تختار الفئة لا اسم النموذج،
         * حتى يتغير النموذج من الإعدادات دون لمس خط الأنابيب.
         */
        /*
         * الأسماء هنا يجب أن تطابق ما يعرضه GET /models لهذا الحساب.
         * اسم غير موجود يسقط بصمت إلى النموذج الافتراضي، فتعمل أصعب مرحلة
         * على أضعف نموذج دون أن يظهر خطأ. حدث هذا فعلًا مع deepseek-reasoner.
         */
        'tiers' => [
            'economy' => env('DEEPSEEK_MODEL_ECONOMY', 'deepseek-v4-flash'),
            // الأقسام على flash (سريع، يكفيها) والتركيب على pro (أدق تفكير).
            // وضع pro على كل مرحلة يجعل التشغيل بطيئًا جدًا دون فائدة تُذكر.
            'standard' => env('DEEPSEEK_MODEL_STANDARD', 'deepseek-v4-flash'),
            'advanced' => env('DEEPSEEK_MODEL_ADVANCED', 'deepseek-v4-pro'),
        ],
    ],

    /*
     * التسعير بالدولار لكل مليون رمز، لملء ai_usage_records.
     * تُراجع القيم عند تغيير أسعار المزود.
     */
    'pricing' => [
        'default' => [
            'input' => (float) env('AI_PRICE_INPUT', 0.28),
            'output' => (float) env('AI_PRICE_OUTPUT', 0.42),
        ],
        'deepseek-v4-flash' => [
            'input' => (float) env('AI_PRICE_FLASH_INPUT', 0.28),
            'output' => (float) env('AI_PRICE_FLASH_OUTPUT', 0.42),
        ],
        'deepseek-v4-pro' => [
            'input' => (float) env('AI_PRICE_PRO_INPUT', 0.55),
            'output' => (float) env('AI_PRICE_PRO_OUTPUT', 2.19),
        ],
    ],

    /*
     * حد إعادة تشغيل القسم عند فشل التحقق من المخطط.
     * القاعدة: مخرج غير صالح لا يُعرض، ويُعاد ضمن هذا الحد فقط.
     */
    'schema_retries' => (int) env('AI_SCHEMA_RETRIES', 2),

    /*
     * إعادة محاولة النقل تعالج الانقطاع العابر فقط.
     * يجب أن يبقى (المهلة × (المحاولات+1)) أقل من مهلة المهمة في RunToolPipeline.
     */
    'transport_retries' => (int) env('AI_TRANSPORT_RETRIES', 2),
    'transport_retry_sleep_ms' => (int) env('AI_TRANSPORT_RETRY_SLEEP_MS', 1500),
];
