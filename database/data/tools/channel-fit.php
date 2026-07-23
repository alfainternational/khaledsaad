<?php

use App\Services\Tools\PipelineSchemas;

return [
    'key' => 'channel-fit',
    'name' => 'Channel Fit',
    'title' => 'لا تعرف أين تركّز جهدك',
    'description' => 'نحدد المكان الذي يوجد فيه عميلك ويناسب وقتك وميزانيتك.',
    'pain' => 'موجود في كل مكان بجهد قليل، فلا تنجح في أي مكان.',
    'promise' => 'منصة أو منصتان تركز فيهما، وسبب واضح لاختيارهما.',
    'audience' => 'لمن يوزّع جهده على منصات كثيرة بلا نتيجة واضحة من أي منها.',
    'duration_minutes' => 8,
    'category' => 'أين تسوّق',
    'sort_order' => 7,
    'status' => 'published',

    'version' => [
        'credit_cost' => 5,
        'output_schema' => PipelineSchemas::synthesis(),

        'scoring_rules' => [
            'rules' => [
                ['field' => 'active_channels', 'label' => 'عدد القنوات', 'type' => 'map', 'weight' => 16,
                    'map' => ['none' => 0, 'one_two' => 1, 'three_four' => 0.6, 'many' => 0.25]],
                ['field' => 'weekly_hours', 'label' => 'وقت متاح معلن', 'type' => 'present', 'weight' => 12],
                ['field' => 'who_executes', 'label' => 'منفّذ واضح', 'type' => 'map', 'weight' => 16,
                    'map' => ['nobody' => 0, 'me_sometimes' => 0.4, 'me_regular' => 0.7, 'team' => 1]],
                ['field' => 'best_channel_today', 'label' => 'معرفة أفضل قناة', 'type' => 'map', 'weight' => 20,
                    'map' => ['unknown' => 0, 'feeling' => 0.4, 'measured' => 1]],
                ['field' => 'customer_location', 'label' => 'مكان العميل محدد', 'type' => 'count', 'target' => 2, 'weight' => 18],
                ['field' => 'monthly_budget', 'label' => 'ميزانية معلنة', 'type' => 'present', 'weight' => 18],
            ],
        ],

        'section_plan' => [
            ['key' => 'focus', 'title' => 'أين تركّز، وماذا توقف', 'tier' => 'advanced'],
            ['key' => 'capacity', 'title' => 'هل خطتك تناسب وقتك فعلًا؟', 'tier' => 'standard'],
            ['key' => 'proof', 'title' => 'كيف تعرف أنها نجحت؟', 'tier' => 'standard'],
        ],
    ],

    'fields' => [
        ['key' => 'customer_location', 'label' => 'أين يقضي عميلك وقته؟', 'type' => 'multiselect', 'step' => 1,
            'step_title' => 'أين عميلك',
            'help' => 'اختر ما تعرفه فعلًا، لا ما هو مشهور.',
            'why' => 'التركيز يبدأ من مكان العميل لا من شعبية المنصة. أفضل منصة في العالم لا تنفع إن لم يكن جمهورك فيها.',
            'options' => [
                ['value' => 'instagram', 'label' => 'Instagram'],
                ['value' => 'tiktok', 'label' => 'TikTok'],
                ['value' => 'snapchat', 'label' => 'Snapchat'],
                ['value' => 'x', 'label' => 'X'],
                ['value' => 'linkedin', 'label' => 'LinkedIn'],
                ['value' => 'google', 'label' => 'يبحث في جوجل'],
                ['value' => 'whatsapp', 'label' => 'واتساب'],
                ['value' => 'offline', 'label' => 'أماكن على أرض الواقع'],
            ]],

        ['key' => 'active_channels', 'label' => 'على كم منصة تعمل اليوم؟', 'type' => 'select', 'step' => 1,
            'why' => 'الانتشار الواسع بجهد صغير يعطي حضورًا باهتًا في كل مكان. التركيز يصنع أثرًا يُلاحظ.',
            'options' => [
                ['value' => 'none', 'label' => 'لا شيء منتظم'],
                ['value' => 'one_two', 'label' => 'واحدة أو اثنتان'],
                ['value' => 'three_four', 'label' => 'ثلاث أو أربع'],
                ['value' => 'many', 'label' => 'أكثر من أربع'],
            ]],

        ['key' => 'best_channel_today', 'label' => 'هل تعرف أي منصة تجلب لك عملاء فعلًا؟', 'type' => 'select', 'step' => 2,
            'step_title' => 'ما ينجح معك الآن',
            'why' => 'من لا يعرف مصدر عملائه يوقف أحيانًا أنجح قناة لديه ويستمر في الأضعف. هذه أغلى غلطة شائعة.',
            'options' => [
                ['value' => 'unknown', 'label' => 'لا أعرف من أين يأتون'],
                ['value' => 'feeling', 'label' => 'إحساس دون أرقام'],
                ['value' => 'measured', 'label' => 'أعرف بالأرقام'],
            ]],

        ['key' => 'best_channel_name', 'label' => 'إن كنت تعرفها، ما هي؟', 'type' => 'text', 'step' => 2,
            'required' => false, 'visible_when' => ['best_channel_today' => ['feeling', 'measured']],
            'why' => 'حتى نبني الخطة على ما ينجح عندك بالفعل بدل أن نبدأ من الصفر.'],

        ['key' => 'monthly_budget', 'label' => 'كم تستطيع أن تصرف شهريًا؟ (ريال)', 'type' => 'number', 'step' => 2,
            'profile_key' => 'monthly_budget', 'validation' => 'min:0',
            'help' => 'اكتب صفرًا إن كنت تعتمد على الجهد فقط.',
            'why' => 'الميزانية تحدد الفرق بين خطة مدفوعة سريعة وخطة عضوية بطيئة. الخطة التي تتجاوز ميزانيتك تبقى حبرًا على ورق.'],

        ['key' => 'weekly_hours', 'label' => 'كم ساعة أسبوعيًا تستطيع أن تعطيها للتسويق؟', 'type' => 'number', 'step' => 3,
            'step_title' => 'وقتك وطاقتك', 'validation' => 'min:0|max:80',
            'why' => 'أكثر الخطط تفشل لأنها كُتبت لشخص لديه ضعف وقتك. نريد خطة تنجح بالوقت الذي تملكه فعلًا.'],

        ['key' => 'who_executes', 'label' => 'من ينفّذ؟', 'type' => 'select', 'step' => 3,
            'why' => 'المنفّذ يحدد نوع المحتوى الممكن. من ينفذ وحده وقت ما يقدر لا يستطيع التزامًا يوميًا مهما كانت الخطة جيدة.',
            'options' => [
                ['value' => 'nobody', 'label' => 'لا أحد بانتظام'],
                ['value' => 'me_sometimes', 'label' => 'أنا وقت ما أقدر'],
                ['value' => 'me_regular', 'label' => 'أنا بانتظام'],
                ['value' => 'team', 'label' => 'فريق أو شخص مخصص'],
            ]],

        ['key' => 'tried_and_failed', 'label' => 'ما القناة التي جرّبتها ولم تنجح؟', 'type' => 'textarea', 'step' => 3,
            'required' => false,
            'help' => 'اذكر ما جرّبت وكم استمررت.',
            'why' => 'كثير من القنوات تُترك قبل أن تُعطى وقتها الكافي. نريد أن نفرّق بين قناة لا تناسبك وقناة لم تُعطَ فرصة.'],
    ],

    'prompts' => [
        'gaps' => <<<'PROMPT'
        افحص بيانات اختيار القنوات واستخرج النواقص والتعارضات.

        انتبه تحديدًا إلى:
        - عدد قنوات كبير مع ساعات أسبوعية قليلة أو منفّذ غير منتظم.
        - نية إعلان مدفوع مع ميزانية صفر.
        - قناة تُركت بعد مدة قصيرة جدًا واعتُبرت فاشلة.
        - عمل على منصات لا يوجد فيها العميل بحسب إجابته نفسها.

        أعد كائن JSON بالمفتاحين missing وconflicts فقط.
        PROMPT,

        'section:focus' => <<<'PROMPT'
        حدد أين يركّز وماذا يوقف.

        اكتب headline ثم points تغطي:
        - القناة الأولى التي يجب أن تأخذ أغلب جهده، مع سبب مرتبط بجمهوره وميزانيته ووقته معًا.
        - قناة يعمل عليها اليوم ويجب إيقافها أو تقليلها، مع سبب صريح.
        - إن كانت قناة سابقة تُركت مبكرًا، اذكر هل تستحق محاولة ثانية بشروط محددة.

        لا تقترح إضافة قناة جديدة إن كانت طاقته لا تكفي القائم أصلًا.
        PROMPT,

        'section:capacity' => <<<'PROMPT'
        قِس الخطة على وقته الحقيقي.

        اكتب headline ثم points تغطي:
        - ما الذي يمكن إنجازه فعليًا بعدد الساعات التي ذكرها، بالعدد لا بالوصف.
        - أول ما يجب حذفه من عبئه الحالي ليصبح التركيز ممكنًا.
        - أبسط إيقاع نشر يستطيع الالتزام به ثلاثة أشهر متواصلة.

        احسب من الساعات التي ذكرها حصرًا. أي تقدير للوقت يحمل is_assumption = true.
        PROMPT,

        'section:proof' => <<<'PROMPT'
        اربط التركيز بدليل نجاح.

        اكتب headline ثم points تغطي:
        - المؤشر الوحيد الذي يتابعه أسبوعيًا ليعرف أن التركيز يعمل.
        - أبسط طريقة ليعرف من أين جاء كل عميل، بأدواته الحالية وبلا تكلفة.
        - المدة التي يجب أن يصبر عليها قبل الحكم على القناة.
        PROMPT,

        'consistency' => <<<'PROMPT'
        راجع الأقسام وابحث عن خطة تتجاوز الساعات أو الميزانية المعلنة،
        أو مؤشر يحتاج أداة لم يذكر امتلاكها.

        أعد قائمة issues فقط.
        PROMPT,

        'synthesis' => <<<'PROMPT'
        ركّب تقرير ملاءمة القنوات.

        قواعد إلزامية:
        - الدرجة محسوبة مسبقًا. لا تعد حسابها.
        - سمِّ قناة واحدة أساسية وقناة داعمة على الأكثر. لا تعطه قائمة طويلة.
        - كل توصية تلتزم بساعاته وميزانيته المعلنة. تجاوزهما شرط رفض لا تفضيل.
        - إن كان لا يعرف مصدر عملائه، فالتوصية الأولى إلزامًا هي تركيب القياس.
        - next_step قرار واحد: ما الذي يوقفه هذا الأسبوع.
        PROMPT,
    ],
];
