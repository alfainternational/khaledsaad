<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI provider (gemini | nvidia | fallback)
    |--------------------------------------------------------------------------
    | fallback: جرّب Google Gemini أولاً ثم NVIDIA NIM عند الفشل أو غياب المفتاح.
    */
    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        /* الكاش لتقليل الإنفاق على الـ API (انظر CachingAiGateway). */
        'cache' => env('AI_CACHE', true),
        'cache_ttl_minutes' => env('AI_CACHE_TTL_MINUTES', 1440),
        /*
         | تطبيق رصيد الـ credits قبل نداء LLM. افتراضياً false حتى لا تنقطع الخدمة
         | عن الحسابات قبل تهيئة الأرصدة؛ الاستهلاك يُسجَّل دائماً في ai_credits_ledger.
         */
        'enforce_credits' => env('AI_ENFORCE_CREDITS', false),
        /* مفتاح إيقاف فوري لكل نداءات الذكاء الخارجية (يُدار من لوحة الآدمن). */
        'kill_switch' => env('AI_KILL_SWITCH', false),
        /* Cascade: تصعيد للـ LLM فقط عند ثقة محلية أقل من العتبة. */
        'cascade' => env('AI_CASCADE', true),
        'cascade_threshold' => env('AI_CASCADE_THRESHOLD', 60),
        /* قاضي الجودة (Gemini): تقييم جودة المضمون لا الطول للحقول الحدّية. */
        'quality_judge' => env('AI_QUALITY_JUDGE', true),

        /*
        | حوكمة الذكاء الخارجي — مصدر الحقيقة الواحد لحدّه.
        | المبدأ: الفهم والتحليل والاستنباط تُنجَز *محلياً* (Semantic + Reasoning +
        | Knowledge). الذكاء الخارجي محصور في ثلاثة أدوار فقط، لا يُنتج حقائق بنيوية:
        |   - knowledge_fetch : جلب معرفة/إشارات سوق حيّة (WebResearch).
        |   - enrichment      : تطوير وصياغة فوق الأساس المحلي (synthesize/cascade/studio).
        |   - review          : مراجعة الجودة قبل التقديم النهائي (QualityJudge/OutputQualityGate).
        | أي دور خارج هذه الثلاثة مرفوض معمارياً.
        */
        'external_roles' => ['knowledge_fetch', 'enrichment', 'review'],
        'local_first' => env('AI_LOCAL_FIRST', true),
    ],

    /*
    | البحث الحيّ في الإنترنت. الافتراضي DuckDuckGo بلا مفتاح؛ غيّره لمزوّد
    | بمفتاح (brave/serpapi) لاحقاً عبر AI_SEARCH_PROVIDER دون لمس الكود.
    */
    'web_search' => [
        'provider' => env('AI_SEARCH_PROVIDER', 'duckduckgo'),
        /* حقن إشارات سوق حيّة داخل تحليل الأدوات المعتمدة على بيانات السوق. */
        'enrich_tools' => env('AI_SEARCH_ENRICH_TOOLS', true),
    ],

    /*
    | تقييم مدخلات الأدوات: طبقة اختيارية لصقل الحكم والملاحظة الاستراتيجية عبر LLM (الأرقام تبقى من المحرك المنظم).
    */
    'ai_tool_assessment' => [
        'enrich_narrative_with_llm' => env('AI_TOOL_ASSESSMENT_ENRICH_LLM', true),
    ],

    'gemini' => [
        'key' => env('GOOGLE_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'temperature' => env('GEMINI_TEMPERATURE', 0.35),
        'max_output_tokens' => env('GEMINI_MAX_OUTPUT_TOKENS', 1024),
        /* الأمان: أبقِه true في الإنتاج. false فقط لتطوير محلي به مشاكل DNS/CA. */
        'verify_tls' => env('GEMINI_VERIFY_TLS', true),
    ],

    'nvidia' => [
        'key' => env('NVIDIA_API_KEY'),
        'base_url' => env('NVIDIA_API_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
        /* نموذج قوي وسريع كاحتياطي موثوق (بُرهن: off_topic صحيح في ~1.6s). */
        'model' => env('NVIDIA_MODEL', 'meta/llama-3.1-70b-instruct'),
        'temperature' => env('NVIDIA_TEMPERATURE', 0.7),
        'max_tokens' => env('NVIDIA_MAX_TOKENS', 8192),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal (Subscriptions API v1)
    |--------------------------------------------------------------------------
    | أنشئ خطط الاشتراك في لوحة PayPal ثم ضع معرف كل خطة (Plan ID) إما في قاعدة
    | البيانات (حقول الخطة في لوحة الإدارة) أو في plan_map أدناه / ملف البيئة.
    | مرجع الموقع القديم: PayPalService + AdminSettings (paypal_plan_*).
    */
    'paypal' => [
        /* إن لم تُضبط: يُعتبر مفعّلاً عند وجود client_id و client_secret (انظر PayPalService::isConfigured) */
        'enabled' => env('PAYPAL_ENABLED'),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'plan_map' => [
            /* إن تُركت فارغة: تُستخدم معرّفات خطة pro (نفس منتج PayPal لعدة مستويات عرض في التطبيق) */
            'starter' => [
                'monthly' => env('PAYPAL_PLAN_STARTER_MONTHLY') ?: env('PAYPAL_PLAN_PRO_MONTHLY'),
                'annual' => env('PAYPAL_PLAN_STARTER_ANNUAL') ?: env('PAYPAL_PLAN_PRO_ANNUAL'),
            ],
            'pro' => [
                'monthly' => env('PAYPAL_PLAN_PRO_MONTHLY'),
                'annual' => env('PAYPAL_PLAN_PRO_ANNUAL'),
            ],
            'team' => [
                'monthly' => env('PAYPAL_PLAN_TEAM_MONTHLY') ?: env('PAYPAL_PLAN_PRO_MONTHLY'),
                'annual' => env('PAYPAL_PLAN_TEAM_ANNUAL') ?: env('PAYPAL_PLAN_PRO_ANNUAL'),
            ],
            /* agency: يدعم أسماء الموقع القديم PAYPAL_PLAN_ENT_* */
            'agency' => [
                'monthly' => env('PAYPAL_PLAN_AGENCY_MONTHLY', env('PAYPAL_PLAN_ENT_MONTHLY')),
                'annual' => env('PAYPAL_PLAN_AGENCY_ANNUAL', env('PAYPAL_PLAN_ENT_ANNUAL')),
            ],
        ],
    ],

];
