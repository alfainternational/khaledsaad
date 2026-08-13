<?php

/*
 * سجل اللغات — مصدر الحقيقة الوحيد لكل ما يتعلق بتعدد اللغات.
 *
 * سبب وجوده: اللغة ليست رمزًا نصيًّا فقط. كل لغة تجرّ معها اتجاه كتابة،
 * وعائلة خط، وصيغة `og:locale`، ونبرة ترجمة تختلف عن غيرها. توزيع هذه
 * الحقائق على القوالب والمتحكّمات يجعل إضافة لغة سادسة تعديلًا في عشرين
 * ملفًا — وهو بالضبط ما يجعل اللغات الجديدة لا تُضاف.
 *
 * القاعدة: لغة المصدر عربية دائمًا. مفاتيح الترجمة هي النص العربي نفسه،
 * فلا يوجد `lang/ar.json` ولا حاجة إليه: المفتاح المفقود يُرجع نفسه.
 */

return [

    /*
     * لغة المصدر: ما يُكتب في القوالب أصلًا، وما يُترجَم *منه* لا إليه.
     */
    'source' => 'ar',

    /*
     * تغليف نصوص Blade تلقائيًا عند التصريف.
     *
     * تعطيله يعيد الموقع إلى العربية الصرفة فورًا دون تراجع عن أي كود —
     * صمّام أمان إن ظهر عطل عرضٍ في الإنتاج. يتبعه `view:clear` دائمًا،
     * لأن القوالب المُصرَّفة لا تُبطَل بتغيّر إعداد.
     */
    'blade_auto_wrap' => env('I18N_BLADE_AUTO_WRAP', true),

    /*
     * اللغات المفعّلة فعليًا: تظهر في مبدّل اللغة، وتُبنى لها ملفات ترجمة،
     * ويُصدر لها `hreflang`. إضافة لغة = إضافة رمزها هنا + تشغيل
     * `php artisan i18n:translate --locale=<code>`. لا شيء غير ذلك.
     */
    'enabled' => ['ar', 'en', 'fr'],

    /*
     * الكتالوج الكامل: لغات جاهزة للتفعيل دون كتابة كود. وجودها هنا لا
     * يكلّف شيئًا — التكلفة تبدأ عند إدراجها في `enabled` وبناء ترجمتها.
     */
    'supported' => [

        'ar' => [
            'native' => 'العربية',
            'english' => 'Arabic',
            'dir' => 'rtl',
            'html' => 'ar',
            'og' => 'ar_SA',
            'font' => 'arabic',
            // نبرة الترجمة: تُمرَّر للنموذج حرفيًا. لغة المصدر لا تُترجَم،
            // لكن النبرة موصوفة هنا ليقرأها المترجم كمرجع للأصل.
            'tone' => 'لهجة بيضاء بلمسة خليجية، مهنية بلا فصحى تقريرية جافة.',
            // نبرة التوليد: غير نبرة الترجمة عمدًا. تلك تصف كيف يُنقل نصّ
            // قصير كُتب أصلًا، وهذه تصف كيف يُكتب تقرير كامل من الصفر —
            // والخلط بينهما يعطي تسمية زر مكتوبة بنبرة تقرير أو العكس.
            'generation' => 'لهجة بيضاء عربية بلمسة خليجية خفيفة: دافئة ومفهومة لأي قارئ عربي، '
                .'بضمير «أنت»، بلا تعابير محلية ثقيلة تخصّ بلدًا بعينه.',
        ],

        'en' => [
            'native' => 'English',
            'english' => 'English',
            'dir' => 'ltr',
            'html' => 'en',
            'og' => 'en_US',
            'font' => 'latin',
            'tone' => 'Professional B2B SaaS English. Direct, concrete, no marketing fluff. '
                .'Sentence case for UI labels, imperative mood for buttons. '
                .'Prefer short nouns over gerunds: "Reports" not "Reporting".',
            'generation' => 'Plain, warm business English addressed directly to the reader as "you". '
                .'Short sentences. Explain every marketing term in the same sentence you use it. '
                .'No jargon, no hype, no motivational filler.',
        ],

        'fr' => [
            'native' => 'Français',
            'english' => 'French',
            'dir' => 'ltr',
            'html' => 'fr',
            'og' => 'fr_FR',
            'font' => 'latin',
            'tone' => 'Français professionnel B2B. Vouvoiement systématique. '
                .'Typographie française correcte : espace insécable avant : ; ! ? et guillemets « ». '
                .'Impératif pour les boutons, majuscule uniquement en début de libellé.',
            'generation' => 'Français professionnel simple et chaleureux, vouvoiement systématique, '
                .'en s’adressant directement au lecteur. Phrases courtes. '
                .'Expliquez chaque terme marketing dans la phrase même où vous l’employez. '
                .'Typographie française correcte : espace insécable avant : ; ! ?',
        ],

        'es' => [
            'native' => 'Español',
            'english' => 'Spanish',
            'dir' => 'ltr',
            'html' => 'es',
            'og' => 'es_ES',
            'font' => 'latin',
            'tone' => 'Español profesional B2B, tratamiento de usted. '
                .'Imperativo en botones, mayúscula solo inicial en etiquetas.',
        ],

        'tr' => [
            'native' => 'Türkçe',
            'english' => 'Turkish',
            'dir' => 'ltr',
            'html' => 'tr',
            'og' => 'tr_TR',
            'font' => 'latin',
            'tone' => 'Profesyonel B2B Türkçe. Kısa ve doğrudan; düğmelerde emir kipi.',
        ],

        'de' => [
            'native' => 'Deutsch',
            'english' => 'German',
            'dir' => 'ltr',
            'html' => 'de',
            'og' => 'de_DE',
            'font' => 'latin',
            'tone' => 'Professionelles B2B-Deutsch, Siezen. Substantive großschreiben, '
                .'Imperativ auf Schaltflächen, keine Anglizismen wo ein deutsches Wort existiert.',
        ],

        'ur' => [
            'native' => 'اردو',
            'english' => 'Urdu',
            'dir' => 'rtl',
            'html' => 'ur',
            'og' => 'ur_PK',
            'font' => 'arabic',
            'tone' => 'پیشہ ورانہ اردو، براہِ راست اور مختصر۔',
        ],
    ],

    /*
     * كيف تُحدَّد اللغة، بالترتيب. أول مصدر يُرجع لغة مدعومة يفوز.
     *
     * `query` أولًا لأن مبدّل اللغة رابط، والرابط يجب أن يعمل عند مشاركته.
     */
    'detection' => [
        'query' => 'lang',
        'cookie' => 'ks_locale',
        'cookie_days' => 365,

        /*
         * `header` غير مُدرَج عمدًا.
         *
         * ترويسة `Accept-Language` تصف نظام الزائر لا لغته. والزائر
         * الخليجي كثيرًا ما يحمل هاتفًا إنجليزيًّا وهو يقرأ عربيًّا —
         * فتقديمُها يعطي أغلبَ السوق المستهدف واجهةً لم يطلبها في منتج
         * لغته الأم عربية، ويُخفي عنه النسخة التي كُتبت له أصلًا.
         *
         * أضِف `'header'` في آخر القائمة إن صارت حركة اللغات الأخرى
         * تستحق التخمين. الاختيار الصريح يبقى مقدَّمًا عليها دائمًا.
         */
        'order' => ['query', 'user', 'cookie'],
    ],

    /*
     * ما يُستخرَج ويُترجَم، وما يُترَك.
     */
    'scan' => [

        'blade' => [
            'roots' => ['resources/views'],
            // قوالب لا تُترجَم: المخرجات القانونية والتقنية التي يجب أن
            // تبقى بلغة المصدر، وقوالب البريد التي يوفّرها الإطار.
            'exclude' => [
                'resources/views/vendor',
                'resources/views/site/content/llms.blade.php',
            ],
            /*
             * سمات HTML التي يقرأها المستخدم أو قارئ الشاشة. غيرها لا
             * يُلمس: `class` وبقية `data-*` عقود برمجية لا نصوص.
             *
             * `data-*` ليست كلها عقودًا: ثلاثٌ منها نصٌّ معروض بحقّ، وكانت
             * تُترك لأن القاعدة نظرت إلى شكل السمة لا إلى من يقرأها —
             *   · `data-label`   → `workspace.css` يعرضها بـ`content: attr(…)`
             *                      فهي عناوين أعمدة الجداول على الجوال
             *   · `data-confirm` → `layouts/app` يقرأها ويعرضها في نافذة التأكيد
             *   · `data-tour`    → نصّ جولة التعريف في لوحة التحكم
             * أي `data-*` أخرى تبقى خارج القائمة: `data-table` و`data-copy-*`
             * وأمثالها مفاتيح يقرأها JavaScript، وترجمتها تكسر السلوك.
             */
            'attributes' => [
                'aria-label', 'aria-description', 'aria-placeholder',
                'placeholder', 'title', 'alt', 'label', 'summary',
                'data-label', 'data-confirm', 'data-tour',
            ],
        ],

        /*
         * ملفات إعداد نصوصها معروضة للزائر، تُقرأ عبر `TranslatedConfig`.
         *
         * تُستخرَج ولا تُغلَّف: `__()` داخل ملف إعداد تُنفَّذ عند الإقلاع
         * وتُخبَز مع `config:cache`، فتتجمّد لغة الموقع كله.
         */
        'config' => [
            'files' => [
                'config/brand.php',
                'config/legal.php',
            ],
        ],

        /*
         * نصوص الواجهة داخل JavaScript.
         *
         * تُستخرَج من نداءات `t('نصّ')` وحدها، لا من كل سلسلة عربية: ملفات
         * JS تحمل تعليقات وأسماء أحداث ومفاتيح `dataset`، وتغليفها بالمسح
         * الشامل يترجم عقودًا. القرار هنا كما في PHP — يمرّ بيد كاتب الملف.
         */
        'js' => [
            'roots' => ['resources/js'],
            'keys' => 'lang/_source/js-keys.json',
        ],

        'php' => [
            /*
             * ملفات يُمنع `i18n:wrap-php` من لمسها مهما كان المسار المطلوب.
             *
             * سبب وجود القائمة أن العطل هنا صامت وعكسيّ: تغليف نصّ واجهة
             * منسيّ يُظهر عربيًّا في شاشة إنجليزية — مزعج ومرئيّ. أما تغليف
             * برومبت أو معجم مطابقة فيغيّر **ما يُطلب من النموذج** أو يكسر
             * المطابقة نفسها، في اللغة الأخرى وحدها، بلا خطأ واحد في السجل.
             * ومن يُشغّل `i18n:wrap-php app/Modules` لاحقًا لن يعرف الفرق.
             *
             * القاعدة: كل ما لا يقرأه إنسان من شاشة يدخل هنا.
             */
            'never_wrap' => [
                // برومبتات تُرسَل للنماذج: ترجمتها تغيّر المطلوب لا العرض.
                'app/Modules/Intake/Assist/GatewayAssistEngine.php',
                'app/Modules/Execution/TaskGuideDeveloper.php',
                'app/Services/Growth/SyntheticAudience.php',
                'app/Services/Growth/GrowthSchemas.php',

                // أسئلة الاستطلاع: تُطرح على النماذج بلسان مشترٍ عربي حقيقي،
                // وترجمتها تقيس سؤالًا آخر (§٤.٢).
                'app/Modules/AiReadiness/QuestionBank.php',

                // معاجم مطابقة: السلسلة فيها مفتاح بحث لا نصّ.
                'app/Modules/AiReadiness/BrandMatcher.php',
                'app/Modules/Execution/DeterministicExampleFactory.php',
                'app/Services/Growth/NextToolSuggester.php',

                /*
                 * مخرجات الطباعة: محتواها يتبع لغة التقرير الآن، لكن نصوصها
                 * تُغلَّف **بيد** لا بمسح شامل. الملفات هنا تركيبُ مستندات
                 * لا نصوصُ واجهة، وأكثر سلاسلها مفاتيح أقسام وعناوين حقول.
                 */
                'app/Modules/Reporting',
                'app/Http/Controllers/Site/ProfilePdfController.php',
            ],

            'roots' => ['app', 'config'],
            'exclude' => [
                // منطق المطابقة العربية: سلاسله معجم لا واجهة. ترجمتها
                // تكسر القياس نفسه (§٤ من CLAUDE.md).
                'app/Modules/Shared/Text/ArabicText.php',
                'app/Modules/Intake/Fitness/MarkerLexicon.php',
                'app/Support/ProductQuality/NeutralArabicScanner.php',
                'app/Modules/Shared/I18n',

                /*
                 * كان هنا استثناء لمخرجات PDF بحجّة أنها «تبقى عربية».
                 * سقطت الحجّة حين صار التقرير يحمل لغته: ملفٌ لتقرير فرنسي
                 * يجب أن يُطبع بالفرنسية وباتجاه LTR. الملفات انتقلت إلى
                 * `never_wrap` — فالأمر لا يزال يرفض مسحها شاملًا، لكن ما
                 * يُغلَّف فيها بيدٍ يدخل الكتالوج ويُترجَم كغيره.
                 */
            ],
        ],
    ],

    /*
     * خط أنابيب الترجمة. كلها تُنفَّذ مرة واحدة عند البناء وتُحفظ في
     * `lang/*.json` داخل المستودع — لا استدعاء نموذج واحد وقت الطلب.
     */
    'build' => [
        'batch' => 24,          // نصوص لكل استدعاء: يوازن السياق مع طول المخرج
        'tier' => 'standard',   // الترجمة ليست مهمة اقتصادية: الدقة أهم من السعر
        'max_retries' => 2,
        'catalog' => 'lang/_source/catalog.json',
    ],

    /*
     * المعجم المقفل: مصطلحات لا يجوز للنموذج أن يجتهد فيها.
     *
     * سبب وجوده: أسماء المقاييس في §١٢ من CLAUDE.md عقد لا نص. لو ترجم
     * النموذج `share_of_voice` مرة «part de voix» ومرة «voix partagée»،
     * صار للمقياس اسمان في الواجهة الواحدة — وهو بالضبط ما يمنعه §١٢.
     */
    'glossary' => [

        // لا تُترجَم إطلاقًا في أي لغة: علامات وأسماء منصات.
        'keep' => [
            'خالد سعد', 'Google', 'Search Console', 'Google Business Profile',
            'Google Trends', 'OpenAI', 'Gemini', 'Anthropic', 'Perplexity',
            'ChatGPT', 'Schema', 'PDF', 'API', 'SEO', 'GEO', 'AEO', 'RTL',
            'WhatsApp', 'LinkedIn', 'TikTok', 'Snapchat', 'Instagram', 'PayPal',
        ],

        // مصطلحات المنهجية: ترجمة واحدة ثابتة لكل لغة، لا اجتهاد.
        'terms' => [
            'درجة النضج التسويقي' => ['en' => 'Marketing Maturity Score', 'fr' => 'Score de maturité marketing'],
            'التشخيص' => ['en' => 'Diagnosis', 'fr' => 'Diagnostic'],
            'الدماغ التجاري' => ['en' => 'Business Brain', 'fr' => 'Cerveau commercial'],
            'الجاهزية للذكاء الاصطناعي' => ['en' => 'AI Readiness', 'fr' => 'Préparation à l’IA'],
            'الأصول المملوكة' => ['en' => 'Owned Assets', 'fr' => 'Actifs détenus'],
            'حصة الصوت' => ['en' => 'Share of Voice', 'fr' => 'Part de voix'],
            'معدل الذكر' => ['en' => 'Mention Rate', 'fr' => 'Taux de mention'],
            'معدل الاستشهاد' => ['en' => 'Citation Rate', 'fr' => 'Taux de citation'],
            'التغطية' => ['en' => 'Coverage', 'fr' => 'Couverture'],
            'كفاية المدخلات' => ['en' => 'Input Fitness', 'fr' => 'Qualité des données saisies'],
            'مرصود' => ['en' => 'Measured', 'fr' => 'Mesuré'],
            'مشتق' => ['en' => 'Derived', 'fr' => 'Dérivé'],
            'فرضية' => ['en' => 'Hypothesis', 'fr' => 'Hypothèse'],
            'قائمة الإصلاح' => ['en' => 'Fix List', 'fr' => 'Liste de correctifs'],
            'بطاقة الجاهزية' => ['en' => 'Readiness Card', 'fr' => 'Carte de préparation'],
            'تقرير الزحف' => ['en' => 'Crawl Report', 'fr' => 'Rapport d’exploration'],
            'خريطة المصادر' => ['en' => 'Source Map', 'fr' => 'Carte des sources'],
            'مساحة العمل' => ['en' => 'Workspace', 'fr' => 'Espace de travail'],
            'المشروع' => ['en' => 'Project', 'fr' => 'Projet'],
            'الوكالة' => ['en' => 'Agency', 'fr' => 'Agence'],
            'الرصيد' => ['en' => 'Credits', 'fr' => 'Crédits'],
        ],
    ],
];
