<?php

return [
    'default' => env('AI_PROVIDER', 'deepseek'),

    /*
     * سلسلة المزوّدات بالترتيب: يُجرَّب الأول، فإن سقط جُرِّب الذي يليه.
     *
     * القرار خلفها أن **تدهور الجودة أفضل من التوقف**: تقريرٌ من نموذج
     * أضعف خيرٌ من شاشة «تعذّر التشغيل» بعد ستين سؤالًا. وهذا بالضبط ما
     * وقع: نفد اشتراك المزوّد الوحيد فتوقفت المنصة كلها، ولم يكن خلفه أحد.
     *
     * كل اسم هنا يجب أن يقابل مفتاح إعداد كامل في هذا الملف. والمزوّد بلا
     * مفتاح API يُستبعد من السلسلة عند البناء، فلا يُهدر عليه استدعاء.
     */
    'chain' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AI_PROVIDER_CHAIN', env('AI_PROVIDER', 'deepseek'))),
    ))),

    /*
     * قاطع الدارة: بعد كم عطل متتالٍ يُسحب المزوّد مؤقتًا، وكم يبقى مسحوبًا.
     * نفاد الحصة (402/429) يفتح القاطع من أول مرة — المحاولة الثانية على
     * حساب فارغ فاشلة بيقين لا باحتمال.
     */
    'breaker' => [
        'threshold' => (int) env('AI_BREAKER_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('AI_BREAKER_COOLDOWN', 300),
    ],

    /*
     * سقف الإنفاق اليومي بالدولار على كل المزوّدات.
     *
     * صفر = بلا سقف. عند بلوغ السقف يتوقف التوليد ويبقى كل ما لا يحتاج
     * ذكاءً عاملًا (الوضع المحدود)، ويُعلَن ذلك صراحةً بدل أن تفشل
     * التشغيلات واحدًا واحدًا بلا سبب مفهوم.
     */
    'daily_spend_cap_usd' => (float) env('AI_DAILY_SPEND_CAP_USD', 0),

    /*
     * عتبة التنبيه المبكر على حصة المزوّد. العطل الذي وقع كان يجب أن يصل
     * إلى المشغّل قبل أن يصل إلى مستخدم.
     */
    'quota_alert_threshold' => (float) env('AI_QUOTA_ALERT_THRESHOLD', 0.2),

    /*
     * سقف الإعادة التلقائية لعطلٍ لدينا. بعده يصير التشغيل فشلًا صريحًا
     * يُنظر فيه بيد بشرية — كي لا يدور عطلٌ دائم إلى الأبد فيبقى المستخدم
     * ينتظر وعدًا لا يتحقق.
     */
    'auto_retry_attempts' => (int) env('AI_AUTO_RETRY_ATTEMPTS', 4),

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
     * مزوّد احتياطي متوافق مع واجهة OpenAI. يبقى خارج السلسلة فعليًّا حتى
     * يُضاف مفتاحه من لوحة الإدارة — فوجود الإعداد لا يعني تشغيله.
     */
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'timeout' => (int) env('GROQ_TIMEOUT', 60),
        'tiers' => [
            'economy' => env('GROQ_MODEL_ECONOMY', 'llama-3.3-70b-versatile'),
            'standard' => env('GROQ_MODEL_STANDARD', 'llama-3.3-70b-versatile'),
            'advanced' => env('GROQ_MODEL_ADVANCED', 'llama-3.3-70b-versatile'),
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
