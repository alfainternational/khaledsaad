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
        'provider' => env('AI_PROVIDER', 'private_worker'),
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

        /*
        | سلسلة المزوّدات (provider=chain): يجرّبها بالترتيب حتى ينجح أحدهم.
        | يُدار من الآدمن. الافتراض: Groq (سريع + Llama 3.3 70B) ثم Cerebras ثم
        | NVIDIA كاحتياط. المزوّد بلا مفتاح يُتخطّى تلقائياً.
        */
        'chain' => env('AI_CHAIN', 'groq,cerebras,nvidia'),

        /*
        | ملفات المزوّدات المتوافقة مع OpenAI (Groq/Cerebras/OpenRouter) — بدائل
        | مجانية ممتازة لـGemini. المفاتيح تُدخَل من لوحة الآدمن (SettingsStore).
        | التسجيل المجاني (بلا بطاقة): Groq=console.groq.com · Cerebras=cloud.cerebras.ai
        | · OpenRouter=openrouter.ai. كلها Chat Completions قياسية.
        */
        'providers' => [
            'groq' => [
                'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
                'key' => env('GROQ_API_KEY'),
                'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                'temperature' => env('GROQ_TEMPERATURE', 0.4),
                'max_tokens' => env('GROQ_MAX_TOKENS', 2048),
                'connect_timeout' => env('GROQ_CONNECT_TIMEOUT', 15),
                'timeout' => env('GROQ_TIMEOUT', 45),
            ],
            'cerebras' => [
                'base_url' => env('CEREBRAS_BASE_URL', 'https://api.cerebras.ai/v1'),
                'key' => env('CEREBRAS_API_KEY'),
                'model' => env('CEREBRAS_MODEL', 'llama-3.3-70b'),
                'temperature' => env('CEREBRAS_TEMPERATURE', 0.4),
                'max_tokens' => env('CEREBRAS_MAX_TOKENS', 2048),
                'connect_timeout' => env('CEREBRAS_CONNECT_TIMEOUT', 15),
                'timeout' => env('CEREBRAS_TIMEOUT', 45),
            ],
            'openrouter' => [
                'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
                'key' => env('OPENROUTER_API_KEY'),
                'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
                'temperature' => env('OPENROUTER_TEMPERATURE', 0.4),
                'max_tokens' => env('OPENROUTER_MAX_TOKENS', 2048),
                'connect_timeout' => env('OPENROUTER_CONNECT_TIMEOUT', 15),
                'timeout' => env('OPENROUTER_TIMEOUT', 45),
            ],
        ],
    ],

    /*
    | البحث الحيّ في الإنترنت. الافتراضي DuckDuckGo بلا مفتاح؛ غيّره لمزوّد
    | بمفتاح (brave/serpapi) لاحقاً عبر AI_SEARCH_PROVIDER دون لمس الكود.
    */
    'web_search' => [
        'provider' => env('AI_SEARCH_PROVIDER', 'duckduckgo'),
        /* حقن إشارات سوق حيّة داخل تحليل الأدوات المعتمدة على بيانات السوق. */
        'enrich_tools' => env('AI_SEARCH_ENRICH_TOOLS', true),
        'verified_research' => env('AI_WEB_RESEARCH_ENABLED', false),
        'scheduled_refresh' => env('AI_WEB_RESEARCH_REFRESH_ENABLED', false),
        'max_results' => (int) env('AI_WEB_RESEARCH_MAX_RESULTS', 8),
        'max_fetches_per_run' => (int) env('AI_WEB_RESEARCH_MAX_FETCHES', 3),
        'max_response_bytes' => (int) env('AI_WEB_RESEARCH_MAX_RESPONSE_BYTES', 2097152),
        'freshness_days' => (int) env('AI_WEB_RESEARCH_FRESHNESS_DAYS', 7),
        'refresh_batch_size' => (int) env('AI_WEB_RESEARCH_REFRESH_BATCH_SIZE', 10),
        'searxng_url' => env('AI_WEB_RESEARCH_SEARXNG_URL'),
    ],

    'knowledge' => [
        'structured_store' => env('AI_KNOWLEDGE_STRUCTURED_STORE', false),
        'dual_write' => env('AI_KNOWLEDGE_DUAL_WRITE', false),
        'project_sync' => env('AI_KNOWLEDGE_PROJECT_SYNC', false),
        'retrieval' => env('AI_KNOWLEDGE_RETRIEVAL', false),
        'upload_processing' => env('AI_KNOWLEDGE_UPLOAD_PROCESSING', false),
        'chunked_uploads' => env('AI_KNOWLEDGE_CHUNKED_UPLOADS', false),
        'chunk_bytes' => (int) env('AI_KNOWLEDGE_CHUNK_BYTES', 1048576),
        'chunked_max_bytes' => (int) env('AI_KNOWLEDGE_CHUNKED_MAX_BYTES', 52428800),
        'chunk_session_ttl_minutes' => (int) env('AI_KNOWLEDGE_CHUNK_SESSION_TTL_MINUTES', 120),
        'structured_extraction' => env('AI_KNOWLEDGE_STRUCTURED_EXTRACTION', false),
        'lock_wait_milliseconds' => env('AI_KNOWLEDGE_LOCK_WAIT_MILLISECONDS', 500),
        'mapping_previous_keys' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_KNOWLEDGE_MAPPING_PREVIOUS_KEYS', '')),
        ))),
        'upload_max_bytes' => (int) env('AI_KNOWLEDGE_UPLOAD_MAX_BYTES', 8388608),
        'upload_chunk_chars' => (int) env('AI_KNOWLEDGE_UPLOAD_CHUNK_CHARS', 3500),
        'upload_max_text_chars' => (int) env('AI_KNOWLEDGE_UPLOAD_MAX_TEXT_CHARS', 350000),
        'hybrid_retrieval' => env('AI_KNOWLEDGE_HYBRID_RETRIEVAL', false),
        'embedding_model' => env('AI_KNOWLEDGE_EMBEDDING_MODEL', 'nomic-embed-text'),
        'embedding_model_version' => env('AI_KNOWLEDGE_EMBEDDING_MODEL_VERSION', 'v1'),
        'embedding_batch_size' => (int) env('AI_KNOWLEDGE_EMBEDDING_BATCH_SIZE', 16),
        'embedding_candidate_limit' => (int) env('AI_KNOWLEDGE_EMBEDDING_CANDIDATE_LIMIT', 200),
        'embedding_min_similarity' => (float) env('AI_KNOWLEDGE_EMBEDDING_MIN_SIMILARITY', 0.25),
        'embedding_query_instruction' => env('AI_KNOWLEDGE_EMBEDDING_QUERY_INSTRUCTION', ''),
        'lexical_term_score_cap' => (int) env('AI_KNOWLEDGE_LEXICAL_TERM_SCORE_CAP', 3),
        'semantic_rank_weight' => (int) env('AI_KNOWLEDGE_SEMANTIC_RANK_WEIGHT', 400),
        'embedding_min_dimensions' => (int) env('AI_KNOWLEDGE_EMBEDDING_MIN_DIMENSIONS', 2),
        'embedding_max_dimensions' => (int) env('AI_KNOWLEDGE_EMBEDDING_MAX_DIMENSIONS', 4096),
        'query_embedding_ttl_days' => (int) env('AI_KNOWLEDGE_QUERY_EMBEDDING_TTL_DAYS', 7),
        'evaluation_min_recall' => (float) env('AI_KNOWLEDGE_EVALUATION_MIN_RECALL', 0.8),
        'evaluation_min_mrr' => (float) env('AI_KNOWLEDGE_EVALUATION_MIN_MRR', 0.6),
        'test_mirror_delay_milliseconds' => 0,
        'test_mirror_read_signal_path' => null,
    ],

    'private_worker' => [
        'enabled' => env('AI_PRIVATE_WORKER_ENABLED', false),
        'clock_drift_seconds' => (int) env('AI_PRIVATE_WORKER_CLOCK_DRIFT_SECONDS', 300),
        'nonce_ttl_seconds' => (int) env('AI_PRIVATE_WORKER_NONCE_TTL_SECONDS', 600),
        'lease_seconds' => (int) env('AI_PRIVATE_WORKER_LEASE_SECONDS', 120),
        'max_result_bytes' => (int) env('AI_PRIVATE_WORKER_MAX_RESULT_BYTES', 1048576),
        'gateway_wait_seconds' => (int) env('AI_PRIVATE_WORKER_GATEWAY_WAIT_SECONDS', 8),
        'prefer_for_generation' => env('AI_PRIVATE_WORKER_PREFER_GENERATION', true),
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
        /* مهلات قابلة للضبط من الآدمن — fail-fast بدل حجب طويل. */
        'connect_timeout' => env('GEMINI_CONNECT_TIMEOUT', 15),
        'timeout' => env('GEMINI_TIMEOUT', 45),
    ],

    'nvidia' => [
        'key' => env('NVIDIA_API_KEY'),
        'base_url' => env('NVIDIA_API_BASE_URL', 'https://integrate.api.nvidia.com/v1'),
        /* نموذج قوي وسريع كاحتياطي موثوق (بُرهن: off_topic صحيح في ~1.6s). */
        'model' => env('NVIDIA_MODEL', 'meta/llama-3.1-70b-instruct'),
        'temperature' => env('NVIDIA_TEMPERATURE', 0.7),
        'max_tokens' => env('NVIDIA_MAX_TOKENS', 8192),
        /*
        | معالجة البطء: NVIDIA يتجاوز أحياناً مهلة طويلة (0 بايت)، فتُحجب العملية.
        | مهلة أقصر (افتراضي 45ث) + اتصال أسرع = فشل سريع يسقط للاحتياطي/المحلي بدل
        | الحجب 90ث. النموذج الأخفّ (8b) أسرع من 70b — قابل للاختيار من الآدمن.
        */
        'connect_timeout' => env('NVIDIA_CONNECT_TIMEOUT', 15),
        'timeout' => env('NVIDIA_TIMEOUT', 45),
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

    /*
    | إشعارات push للموبايل عبر FCM HTTP v1.
    | project_id: معرف مشروع Firebase. credentials: مسار ملف service-account JSON.
    | بدون هذين الإعدادين تعمل PushGateway كـ no-op آمن.
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS_PATH'),
    ],

];
