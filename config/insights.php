<?php

/*
 * إحصاءات الزوّار — قياس داخلي بالكامل (§١٠ الفئة أ).
 *
 * لا سكربت طرف ثالث ولا نداء خارجي: مصدر كل رقم هنا هو طلب HTTP وصل
 * إلى هذا التطبيق، أو بيكون من متصفح الزائر نفسه إلى مسارنا. لذلك لا
 * يحجبه مانع إعلانات ولا يخضع لعيّنة، ولا تغادر بيانات زوّارنا الخادم.
 */
return [
    // مفتاح إيقاف واحد: إطفاؤه يوقف الالتقاط كلّه دون حذف ما التُقط.
    'enabled' => (bool) env('INSIGHTS_ENABLED', true),

    /*
     * نافذة الجلسة: طلبٌ بعد هذه المدة من آخر نشاط يبدأ جلسة جديدة.
     * ثلاثون دقيقة هي العرف الصناعي، ووجودها هنا يجعل التعريف قابلًا
     * للمراجعة بدل أن يكون رقمًا مدفونًا في الكود.
     */
    'session_timeout_minutes' => (int) env('INSIGHTS_SESSION_TIMEOUT', 30),

    /*
     * نبض المتصفح بالثواني: كل نبضة تضيف زمنًا **نشطًا** فقط.
     *
     * الفرق جوهري: «مدة البقاء» المحسوبة من فارق وقتَي طلبين تعدّ التبويب
     * المنسيّ مفتوحًا ثلاث ساعات قراءةً. النبض يتوقف عند إخفاء التبويب
     * أو الخمول، فيقيس ما قرأه الزائر لا ما تركه مفتوحًا.
     */
    'heartbeat_seconds' => (int) env('INSIGHTS_HEARTBEAT', 15),

    // بعدها يُعدّ الزائر خاملًا فيتوقّف عدّ الزمن حتى أول حركة.
    'idle_after_seconds' => (int) env('INSIGHTS_IDLE_AFTER', 60),

    /*
     * الاحتفاظ: الصفوف الخام تُحذف بعد هذه المدة، والتجميع اليومي يبقى.
     * صفر = لا حذف. الحذف يجري بأمر `insights:prune` المجدول.
     */
    'retention_days' => (int) env('INSIGHTS_RETENTION_DAYS', 400),

    /*
     * مسارات لا تُسجَّل: البيكون نفسه، والفحوص، والملفات الثابتة.
     * تسجيلها يضاعف الصفوف بلا معلومة — لا أحد «يزور» نقطة جمع.
     */
    'excluded_paths' => [
        '_insights/*',
        'up',
        'livewire/*',
        'build/*',
        'storage/*',
        'webhooks/*',
        'sanctum/*',
        'api/*',
    ],

    /*
     * هل تُحسب زيارات الإداريين ضمن الأرقام العامة؟
     *
     * الافتراضي لا: مالك المنصة يفتح صفحاته عشرات المرات يوميًّا، فيُغرق
     * أرقامه بنفسه. الزيارة تُسجَّل دائمًا وتُعلَّم `is_staff`، والاستبعاد
     * يجري عند القراءة لا عند الكتابة — فيمكن التراجع عنه بلا فقدان بيانات.
     */
    'count_staff' => (bool) env('INSIGHTS_COUNT_STAFF', false),

    /*
     * احترام ترويسة Do Not Track.
     *
     * الافتراضي: لا يمنع الالتقاط. القياس هنا طرف أول بلا عبور مواقع،
     * وعنوان IP يُخزَّن مُجزَّأً لا خامًا، فلا يُبنى منه ملف تعقّب. من أراد
     * التشدّد يرفع القيمة إلى true فيُهمَل الزائر المُعلِن كليًّا.
     */
    'respect_dnt' => (bool) env('INSIGHTS_RESPECT_DNT', false),

    /*
     * تعريف الارتداد: جلسة بصفحة واحدة، بلا حدث تفاعل، وبزمن نشط
     * دون هذا الحدّ. الثواني الخمس تفصل «فتح وأغلق» عن «قرأ ولم يتابع».
     */
    'bounce_max_seconds' => (int) env('INSIGHTS_BOUNCE_SECONDS', 5),

    /*
     * البوتات: تُسجَّل ولا تُخلط.
     *
     * الحذف خسارة — زحف GPTBot وClaudeBot وPerplexityBot هو المصدر الوحيد
     * الذي يجيب «هل أنا مرئي للنماذج أصلًا». لذلك تُخزَّن مصنَّفة في نفس
     * الجداول، وتُستبعد من أرقام البشر عند القراءة.
     */
    'ai_crawlers' => [
        'GPTBot' => 'OpenAI',
        'OAI-SearchBot' => 'OpenAI',
        'ChatGPT-User' => 'OpenAI',
        'ClaudeBot' => 'Anthropic',
        'Claude-Web' => 'Anthropic',
        'anthropic-ai' => 'Anthropic',
        'PerplexityBot' => 'Perplexity',
        'Perplexity-User' => 'Perplexity',
        'Google-Extended' => 'Google',
        'Googlebot' => 'Google',
        'Bingbot' => 'Microsoft',
        'Applebot' => 'Apple',
        'Amazonbot' => 'Amazon',
        'Bytespider' => 'ByteDance',
        'meta-externalagent' => 'Meta',
        'CCBot' => 'Common Crawl',
        'YandexBot' => 'Yandex',
        'DuckDuckBot' => 'DuckDuckGo',
    ],

    /*
     * أنماط عامة تدلّ على آلة لا إنسان، بعد استبعاد المصنَّفين أعلاه.
     *
     * وهذه القائمة **أرضية لا سقف**: من ينتحل سلسلة Chrome عادية يمرّ
     * منها كلها. من يمسكه فعلًا هو شرط التحقّق في `scopeHuman` — أن
     * ينفّذ المتصفّح صفحتنا. تبقى القائمة لأنها تسمّي ما أعلن عن نفسه،
     * وتسميته أنفع من عدّه مجهولًا.
     */
    'bot_patterns' => [
        'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
        'httpclient', 'headless', 'phantomjs', 'monitor', 'uptime', 'preview',
        'facebookexternalhit', 'whatsapp', 'telegrambot', 'twitterbot', 'linkedinbot',
        'embedly', 'scrapy', 'axios/', 'go-http-client', 'okhttp', 'postman',

        // أدوات الأتمتة التي تُعلن عن نفسها: تشغّل جافاسكربت فتتجاوز
        // التحقّق، ولا تتجاوز اسمها حين تكتبه في سلسلة الوكيل.
        'headlesschrome', 'puppeteer', 'playwright', 'selenium', 'webdriver',
        'phantom', 'cypress', 'lighthouse', 'pagespeed', 'chrome-lighthouse',
        'node-fetch', 'libwww-perl', 'java/', 'guzzlehttp', 'apachebench',
        'masscan', 'zgrab', 'nuclei', 'nikto', 'sqlmap', 'wpscan',
    ],

    /*
     * أحداث تُعدّ تحويلًا. القائمة هنا لا في الكود، لأن ما يُعدّ نجاحًا
     * قرار منتج يتغيّر، وتغييره يجب ألّا يحتاج نشرًا.
     */
    'conversion_events' => [
        'signup' => 'إنشاء حساب',
        'login' => 'تسجيل دخول',
        'lead' => 'طلب تواصل',
        'checkout_started' => 'بدء دفع',
        'purchase' => 'دفعة ناجحة',
        'tool_run' => 'تشغيل أداة',
        'diagnosis_started' => 'بدء تشخيص',
        'report_downloaded' => 'تنزيل تقرير',
        'subscribe' => 'اشتراك في النشرة',
    ],
];
