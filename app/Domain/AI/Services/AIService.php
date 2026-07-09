<?php

namespace App\Domain\AI\Services;

use App\Contracts\AiGatewayInterface;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use Illuminate\Support\Str;

class AIService
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{success: bool, response?: string, error?: string, message?: string}
     */
    public function chat(array $messages, ?string $systemPrompt = null): array
    {
        $prompt = '';
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $prefix = match ($role) {
                'user' => 'User: ',
                'system' => 'System: ',
                'assistant' => 'Assistant: ',
                default => '',
            };
            $prompt .= $prefix.($msg['content'] ?? '')."\n\n";
        }

        $response = $this->gateway->requestContent($prompt, $systemPrompt);

        $text = $this->extractAssistantTextFromGatewayResponse($response);
        if ($text !== null && $text !== '') {
            return [
                'success' => true,
                'response' => $text,
            ];
        }

        return [
            'success' => false,
            'error' => 'api_error',
            'message' => 'تعذر الحصول على رد من المستشار الذكي حالياً. حاول مرة أخرى.',
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{success: bool, analysis?: array<string, mixed>, error?: string}
     */
    public function analyzeToolInputs(string $toolCode, string $toolName, array $inputs, ?int $workspaceId = null, ?int $projectId = null): array
    {
        $contextBlock = $this->contextBuilder->promptBlockForIds($workspaceId, $projectId);
        $toolFocus = $this->toolAnalysisFocus($toolCode);

        $inputBlock = collect($inputs)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v, $k) => "- {$k}: {$v}")
            ->implode("\n");

        if (trim($inputBlock) === '') {
            return ['success' => false, 'error' => 'لا توجد مدخلات كافية للتحليل.'];
        }

        $prompt = <<<PROMPT
        أنت مستشار أعمال وتسويق استراتيجي بخبرة 15 سنة. مهمتك تحليل مدخلات أداة "{$toolName}" وتقديم تحليل خبير يساعد المستخدم فعلاً.

        {$contextBlock}

        === ما يجب التركيز عليه في هذه الأداة ===
        {$toolFocus}

        === المدخلات الحالية ===
        {$inputBlock}

        === القواعد الصارمة - اقرأها جيداً ===
        1. لا تكرر ما كتبه المستخدم أبداً! حلّل المدخلات وأضف قيمة جديدة
        2. كل نقطة في strengths يجب أن تشرح "لماذا هذا جيد" وليس فقط "هذا موجود"
        3. كل نقطة في gaps يجب أن تشرح "ما الذي سيحدث إذا لم تعالج هذه النقطة"
        4. كل recommendation يجب أن تبدأ بفعل أمر وتحدد ماذا يفعل وكيف ومتى
        5. الـ strategic_note يجب أن يربط بين هذه الأداة والصورة الكاملة للمشروع
        6. في field_notes: قدّم اقتراح تحسين محدد لا مجرد "يحتاج توضيح"

        === أمثلة على الفرق بين الجيد والسيء ===
        سيء: "المدخلات جيدة بشكل عام"
        جيد: "التركيز على الخبرة كنقطة قوة ذكي لأنها تختصر الثقة، لكن بدون عرض ملموس ستظل خبرة غير مُثمَّرة"

        سيء: "يحتاج تحسين العرض"
        جيد: "حدد بالضبط ما يحصل عليه العميل في أول 7 أيام - هذا يكسر حاجز التردد بنسبة 60%"

        أعد نتيجة JSON فقط بهذا الشكل:
        {{
            "score": رقم من 0 إلى 100,
            "verdict": "حكم تقييمي مختصر (ليس وصفاً بل تقييماً) مثال: 'عرض واعد لكنه يفتقر لعنصر الإلحاح'",
            "strengths": ["اشرح لماذا هذه نقطة قوة وكيف يستثمرها", "..."],
            "gaps": ["اشرح الفجوة + ماذا سيخسر إذا لم يعالجها", "..."],
            "recommendations": ["ابدأ بفعل أمر + ماذا + كيف + متى", "...", "..."],
            "strategic_note": "ملاحظة واحدة تربط هذه الأداة بنجاح المشروع ككل",
            "field_notes": {{
                "اسم_الحقل": "اقتراح تحسين محدد مع مثال إن أمكن"
            }}
        }}

        - أعد JSON فقط بدون أي نص قبله أو بعده
        PROMPT;

        $text = $this->gateway->generateText($prompt);

        if (! $text) {
            return ['success' => false, 'error' => 'تعذر التحليل حالياً. حاول مرة أخرى.'];
        }

        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        if (! is_array($parsed) || ! isset($parsed['score'])) {
            return ['success' => true, 'analysis' => [
                'score' => 50,
                'verdict' => $cleaned,
                'strengths' => [],
                'gaps' => [],
                'recommendations' => [$cleaned],
                'strategic_note' => '',
                'field_notes' => [],
            ]];
        }

        return ['success' => true, 'analysis' => $parsed];
    }

    private function toolAnalysisFocus(string $toolCode): string
    {
        return match ($toolCode) {
            'diagnosis' => <<<'F'
            ركّز على: هل يفرّق بين المشكلة الحقيقية والأعراض؟ ما تكلفة التأخير بالأرقام أو بالأثر؟
            وصِّ بـ: أول إجراء يجب اتخاذه هذا الأسبوع + ما الذي يجب تجاهله الآن + ما الذي يجب مراقبته.
            حذّر من: الخلط بين الأسباب والأعراض، تشتت الأولويات، تأجيل القرار.
            F,
            'idea-clarity' => <<<'F'
            ركّز على: هل الفكرة يمكن شرحها في 10 ثوانٍ؟ هل يوجد دليل أن الناس يدفعون لحل هذه المشكلة؟
            وصِّ بـ: أصغر اختبار يثبت أو ينفي الفكرة خلال أسبوع + ما يجب حذفه من الفكرة لتبسيطها.
            حذّر من: الفكرة العامة جداً، عدم وجود مشكلة حقيقية، الحل يبحث عن مشكلة.
            F,
            'swot-analysis' => <<<'F'
            ركّز على: ما الاستراتيجية الهجومية (قوة × فرصة)؟ ما أخطر سيناريو (ضعف × تهديد)؟
            وصِّ بـ: ما يجب مضاعفته فوراً + ما يجب إلغاؤه فوراً + أول قرار هجومي يجب اتخاذه.
            حذّر من: نقاط قوة وهمية (أمنيات)، تجاهل التهديدات، عدم ربط القوة بالفرصة عملياً.
            اشرح: ماذا سيحدث إذا لم يتحرك خلال 30 يوماً (الخسارة المحتملة).
            F,
            'goal-definition' => <<<'F'
            ركّز على: هل الهدف SMART (محدد، قابل للقياس، واقعي، مؤقت)؟ ما الفجوة بين الوضع الحالي والهدف؟
            وصِّ بـ: المقياس الأسبوعي الذي يثبت التقدم + أول عائق يجب إزالته + نقطة المراجعة الأولى.
            حذّر من: أهداف عامة بدون أرقام، مدد غير واقعية، عدم تحديد المسؤول.
            F,
            'problem-definition' => <<<'F'
            ركّز على: هل هذه المشكلة الحقيقية أم عرض لمشكلة أعمق؟ ما تكلفة عدم الحل شهرياً؟
            وصِّ بـ: السبب الجذري المحتمل + أسرع حل يمكن اختباره خلال أسبوع + من يجب سؤاله للتأكد.
            حذّر من: حل عرض بدلاً من المشكلة، عدم سؤال المتضررين مباشرة، افتراضات غير مثبتة.
            F,
            'tagline-builder' => <<<'F'
            ركّز على: هل الجملة تُفهم خلال 5 ثوانٍ من شخص غريب؟ هل تحتوي وعداً محدداً؟
            وصِّ بـ: اقتراح 2-3 صياغات بديلة أقوى + أفضل مكان لاستخدام الجملة + اختبار A/B مقترح.
            حذّر من: الغموض، الكلمات الفارغة (حلول مبتكرة، خدمات متميزة)، عدم ذكر الفائدة.
            F,
            'ideal-customer' => <<<'F'
            ركّز على: هل هذا عميل يدفع فعلاً أم مهتم فقط؟ هل أعرف أين أجده بدقة؟
            وصِّ بـ: الكلمات التي يستخدمها العميل لوصف مشكلته + أفضل 3 أماكن للوصول إليه + أول رسالة تجذبه.
            حذّر من: وصف عميل مثالي لا يوجد في الواقع، عدم تحديد القناة، الخلط بين المستخدم والمشتري.
            F,
            'positioning' => <<<'F'
            ركّز على: هل يفهم العميل لماذا يختارك أنت بدلاً من المنافس في 10 ثوانٍ؟
            وصِّ بـ: جملة التمركز المقترحة (نحن الـ[فئة] الوحيدة التي [ميزة] لـ[شريحة]) + ما يجب ألا تكونه.
            حذّر من: تمركز داخلي لا يفهمه العميل، ادعاءات عامة (الأفضل، الأول)، عدم وجود دليل.
            F,
            'market-analysis' => <<<'F'
            ركّز على: هل السوق كبير ومتنامٍ؟ أين أسهل نقطة دخول؟ ما أكبر مخاطرة؟
            وصِّ بـ: الشريحة الأسهل للبدء + حجم الفرصة التقريبي + إشارات يجب مراقبتها أسبوعياً.
            حذّر من: سوق صغير جداً، اعتماد على حدس بدلاً من بيانات، تجاهل العوائق القانونية أو التقنية.
            F,
            'competitor-analysis' => <<<'F'
            ركّز على: نقطة ضعف المنافس الأكبر + الفجوة التي لم يدخلها أحد + استراتيجية المواجهة.
            وصِّ بـ: كيف تستغل ضعف المنافس عملياً + ما الذي يفعلونه أفضل وكيف تعوّضه + الميزة المستدامة.
            حذّر من: المبالغة في تقدير ميزتك، تقليد المنافس، عدم وجود ميزة حقيقية.
            F,
            'offer-builder' => <<<'F'
            ركّز على: هل العرض يحل مشكلة حقيقية؟ هل الضمان يقلل مخاطرة العميل؟ ما سبب واحد للرفض وكيف تعالجه؟
            وصِّ بـ: تحسين صياغة العرض + عنصر إلحاح مقترح + أول اختبار للعرض على 10 عملاء.
            حذّر من: بيع ميزات بدلاً من نتائج، عدم وجود ضمان، عرض معقد يصعب شرحه.
            F,
            'pricing-strategy' => <<<'F'
            ركّز على: هل السعر مبني على القيمة لا التكلفة؟ هل يوجد مبرر يقنع العميل؟
            وصِّ بـ: نقطة المقارنة (anchor) المقترحة + استراتيجية الرفع التدريجي + العرض الأول لكسر حاجز الشراء.
            حذّر من: تسعير منخفض يقلل القيمة المحسوسة، عدم وجود عرض مدخلي، حرب أسعار مع المنافسين.
            F,
            'value-ladder' => <<<'F'
            ركّز على: هل الانتقال بين المستويات طبيعي؟ أين نقطة الربح الأكبر؟
            وصِّ بـ: معدل التحويل المتوقع بين المستويات + كيف تدفع العميل للمستوى التالي + أي مستوى تبدأ بالتركيز عليه.
            حذّر من: سلّم بدون مدخل منخفض المخاطرة، فجوة كبيرة بين الأسعار، قيمة غير واضحة بين المستويات.
            F,
            'package-builder' => <<<'F'
            ركّز على: هل الفرق واضح لعميل يقرأها أول مرة؟ أي حزمة ستبيع أكثر؟
            وصِّ بـ: الحزمة التي يجب تسويقها كـ"موصى بها" + هل هناك حزمة ميتة يجب حذفها + كيف تدفع نحو الحزمة الأعلى.
            حذّر من: حزم متشابهة جداً، عدم وجود حزمة "أنكر" للمقارنة، أسعار عشوائية.
            F,
            'promise-builder' => <<<'F'
            ركّز على: هل الوعد قابل للإثبات بدليل؟ هل يميّز عن المنافسين؟
            وصِّ بـ: كيف تثبت الوعد (شهادة، رقم، ضمان) + صياغة بديلة أقوى إن أمكن + أين تستخدم الوعد (إعلان، landing page).
            حذّر من: وعود عامة (النجاح، التميز)، وعود مستحيلة تضر المصداقية، عدم وجود حدود واقعية.
            F,
            'funnel-builder' => <<<'F'
            ركّز على: أين أكبر تسريب في القمع؟ ما أول تحسين يمكن إجراؤه هذا الأسبوع؟
            وصِّ بـ: المقياس الأهم لكل مرحلة + كيف تقلل التسريب في أضعف نقطة + أول A/B test مقترح.
            حذّر من: قمع طويل جداً، عدم وجود نقطة قرار واضحة، تجاهل مرحلة ما بعد الشراء.
            F,
            'customer-journey' => <<<'F'
            ركّز على: أين أكبر نقطة احتكاك؟ ما لحظة "آها"؟ كيف تحوّل راضٍ إلى مُحيل؟
            وصِّ بـ: إزالة أكبر نقطة احتكاك + تسريع لحظة القيمة الأولى + آلية تحويل العميل لسفير.
            حذّر من: رحلة معقدة جداً، نقاط ثقة غير كافية، عدم وجود آلية احتفاظ بعد الشراء.
            F,
            'marketing-plan' => <<<'F'
            ركّز على: هل الخطة قابلة للتنفيذ هذا الأسبوع؟ ما أول 3 مهام محددة؟
            وصِّ بـ: الجدول الزمني المقترح + المقياس الذي يثبت النجاح + خطة B إذا فشل المسار الأول.
            حذّر من: خطة نظرية بدون تواريخ، عدم تحديد مسؤول، عدم وجود ميزانية اختبار.
            F,
            'content-plan' => <<<'F'
            ركّز على: هل كل قطعة محتوى تخدم مرحلة محددة من القمع أم عشوائية؟
            وصِّ بـ: المحتوى الأول الذي يجب إنتاجه + كيف تعيد استخدام كل قطعة 3 مرات + التكرار الواقعي.
            حذّر من: محتوى بدون هدف واضح، تكرار غير واقعي، عدم ربط المحتوى بالبيع.
            F,
            'campaign-builder' => <<<'F'
            ركّز على: هل للحملة هدف واحد واضح؟ ما العنصر الذي تختبره أولاً؟
            وصِّ بـ: ميزانية الاختبار المقترحة + أول A/B test + متى توقف الحملة إذا فشلت.
            حذّر من: أهداف متعددة في حملة واحدة، عدم وجود جمهور محدد، ميزانية بدون حد.
            F,
            'follow-up-sequence' => <<<'F'
            ركّز على: هل يبني ثقة تدريجية أم يبيع من أول رسالة؟ ما التوقيت المثالي بين الرسائل؟
            وصِّ بـ: ترتيب الرسائل المقترح (قيمة → قصة → عرض) + سبب التوقف الأكثر احتمالاً وكيف تمنعه.
            حذّر من: بيع مباشر بدون قيمة مسبقة، تكرار ممل، عدم وجود قاعدة توقف.
            F,
            'kpi-tracker' => <<<'F'
            ركّز على: هل المؤشرات قائدة (تتنبأ) أم تابعة (تصف الماضي)؟ أيها الأهم؟
            وصِّ بـ: المؤشر الواحد الذي يحسّن كل شيء + إجراء الطوارئ عند الانخفاض + دورة المراجعة المقترحة.
            حذّر من: مؤشرات كثيرة بلا فائدة (vanity metrics)، عدم وجود عتبة إنذار، تجاهل المؤشرات القائدة.
            F,
            'execution-plan' => <<<'F'
            ركّز على: هل كل مهمة لها مسؤول ووقت محدد؟ ما أول عائق محتمل؟
            وصِّ بـ: ما يمكن حذفه لتبسيط الخطة + نقطة المراجعة الأولى + كيف تتعامل مع التأخير.
            حذّر من: خطة مثالية لا تتحمل المفاجآت، مهام بدون مسؤول، تبعيات غير واضحة.
            F,
            'performance-review' => <<<'F'
            ركّز على: ما النمط المتكرر؟ ما السبب الجذري الحقيقي (ليس الظاهري)؟
            وصِّ بـ: القرار الواحد الذي يجب اتخاذه بناءً على هذه البيانات + ما يجب إيقافه + ما يجب مضاعفته.
            حذّر من: تحليل مبني على انطباعات لا بيانات، تجاهل الأنماط، عدم ربط الأداء بالقرارات.
            F,
            'smart-recommendations' => <<<'F'
            ركّز على: ما الفعل الواحد الذي يحدث أكبر أثر بأقل موارد؟
            وصِّ بـ: الأولوية #1 هذا الأسبوع + ما يجب التوقف عنه فوراً + الفرصة المخفية في البيانات.
            حذّر من: توصيات عامة بلا سياق، عدم ترتيب الأولويات، تجاهل الموارد المتاحة.
            F,
            'growth-priorities' => <<<'F'
            ركّز على: ما مسار النمو الأقل مخاطرة والأسرع عائداً؟
            وصِّ بـ: شرط الانتقال للمسار التالي + ما يجب تثبيته قبل التوسع + متى يجب التوقف عن هذا المسار.
            حذّر من: التوسع قبل التثبيت، مسارات نمو عشوائية، عدم وجود معيار نجاح محدد.
            F,
            default => <<<'F'
            ركّز على: ما أهم نتيجة عملية من المدخلات؟ ما أكبر مخاطرة؟ ما أول خطوة يجب تنفيذها؟
            وصِّ بـ: الإجراء الأول المحدد + ما يجب تجنبه + المقياس الذي يثبت النجاح.
            حذّر من: مدخلات عامة، عدم وجود هدف واضح، عدم ربط المدخلات بالتنفيذ.
            F,
        };
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $sourceContext
     */
    public function generateSmartSummary(
        string $toolCode,
        string $toolName,
        array $inputs,
        array $sourceContext = [],
        ?int $workspaceId = null,
        ?int $projectId = null,
    ): ?string
    {
        $inputBlock = collect($inputs)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v, $k) => "- {$k}: {$v}")
            ->implode("\n");

        if (trim($inputBlock) === '') {
            return null;
        }

        $profile = $sourceContext['workspace_profile'] ?? [];
        $persona = $profile['persona'] ?? 'غير محدد';
        $goal = $profile['primary_goal'] ?? 'غير محدد';
        $toolDirective = $this->toolSummaryDirective($toolCode);
        $contextBlock = $this->buildGenerationContextBlock($workspaceId, $projectId, $sourceContext);

        $prompt = <<<PROMPT
        أنت محلل استراتيجي محترف. مهمتك تحليل مدخلات أداة "{$toolName}" وإنتاج تقرير مختصر يفيد المستخدم فعلاً.

        {$contextBlock}

        نوع المستخدم: {$persona}
        الهدف الرئيسي: {$goal}

        المدخلات:
        {$inputBlock}

        === ما يجب أن يتضمنه تقريرك لهذه الأداة ===
        {$toolDirective}

        === القواعد الصارمة ===
        - لا تكرر ما كتبه المستخدم كما هو! حلّل وأضف قيمة حقيقية
        - كل نقطة يجب أن تكون توصية عملية أو تحذيراً مهماً أو فرصة مكتشفة
        - اكتب بلغة المستشار الخبير لا الروبوت: "ركّز على..." "احذر من..." "الفرصة هنا هي..."
        - العنوان يجب أن يكون حكماً تقييمياً لا وصفاً (مثال: "عرض قوي يحتاج ضمان أوضح" لا "ملخص العرض")

        أعد الإجابة بصيغة JSON فقط:
        {{
            "headline": "حكم تقييمي مختصر على المدخلات (ليس وصفاً بل تقييماً)",
            "text": "فقرة تحليلية 2-3 جمل تشرح الوضع الحقيقي وأهم ما يجب الانتباه له",
            "bullets": [
                "توصية عملية أولى أو تحذير مهم",
                "توصية عملية ثانية أو فرصة مكتشفة",
                "توصية عملية ثالثة أو خطوة تالية محددة"
            ]
        }}
        PROMPT;

        return $this->gateway->generateText($prompt);
    }

    private function toolSummaryDirective(string $toolCode): string
    {
        return match ($toolCode) {
            'diagnosis' => 'حدد: هل التشخيص يكشف المشكلة الحقيقية أم الأعراض فقط؟ ما أول شيء يجب إصلاحه؟ ما الذي سيخسره إذا تأخر؟ ما أولوية الأسبوع القادم بالضبط؟',
            'idea-clarity' => 'حدد: هل الفكرة جاهزة للبيع أم تحتاج تبسيطاً؟ هل المشكلة حقيقية ويدفع لها الناس؟ ما أكبر مخاطرة في هذه الفكرة؟ ما أول اختبار يجب إجراؤه؟',
            'swot-analysis' => 'حدد: ما الاستراتيجية الهجومية الأفضل (قوة + فرصة)؟ ما الخطر الأكبر (ضعف + تهديد)؟ ما الذي يجب التخلص منه فوراً؟ ما الذي يجب مضاعفته؟ ما الذي سيحدث إذا لم يتحرك الآن؟',
            'goal-definition' => 'حدد: هل الهدف ذكي (محدد + قابل للقياس + واقعي + مؤقت)؟ ما أكبر عائق سيواجهه؟ ما المؤشر الذي يجب مراقبته أسبوعياً؟ متى بالضبط يجب مراجعة التقدم؟',
            'problem-definition' => 'حدد: هل هذه مشكلة حقيقية أم عرض لمشكلة أعمق؟ ما تكلفة عدم حلها شهرياً؟ من المتضرر الأكبر؟ ما الحل الأسرع الذي يمكن اختباره خلال أسبوع؟',
            'tagline-builder' => 'حدد: هل الجملة تُفهم في 5 ثوانٍ؟ هل تحتوي على وعد واضح؟ هل يمكن استخدامها في إعلان مباشرة؟ اقترح صياغة بديلة أقوى إن أمكن.',
            'ideal-customer' => 'حدد: هل هذا عميل يدفع فعلاً أم مجرد مهتم؟ أين يقضي وقته (قنوات محددة)؟ ما الكلمات التي يستخدمها لوصف مشكلته؟ ما أول رسالة تجذبه؟',
            'positioning' => 'حدد: هل التمركز يميّزك عن أقرب 3 منافسين؟ هل العميل سيفهم لماذا يختارك أنت؟ ما الذي ستقوله في 10 ثوانٍ لعميل محتمل؟ ما الذي يجب ألا تكونه؟',
            'market-analysis' => 'حدد: هل السوق كبير بما يكفي؟ هل الطلب متزايد أم متناقص؟ ما أسهل شريحة للدخول إليها أولاً؟ ما أكبر مخاطرة في هذا السوق؟',
            'competitor-analysis' => 'حدد: ما نقطة ضعف المنافس التي يمكن استغلالها الآن؟ ما الذي يفعلونه أفضل منك وكيف تعوّضه؟ هل هناك فجوة لم يدخلها أحد؟ ما استراتيجية المواجهة المقترحة؟',
            'offer-builder' => 'حدد: هل العرض يحل مشكلة حقيقية أم يبيع ميزات؟ هل الضمان يقلل المخاطرة على العميل؟ ما سبب واحد قد يجعل العميل يرفض؟ كيف تعالجه؟ ما أول اختبار للعرض؟',
            'pricing-strategy' => 'حدد: هل السعر مبني على القيمة لا التكلفة؟ هل هناك مبرر واضح يجعل العميل يقبل السعر؟ ما استراتيجية الرفع التدريجي؟ ما العرض الأول لكسر حاجز الشراء؟',
            'value-ladder' => 'حدد: هل هناك انتقال طبيعي بين المستويات؟ هل المدخل المجاني/الرخيص يُظهر القيمة بسرعة؟ ما معدل التحويل المتوقع بين المستويات؟ أين نقطة الربح الأكبر؟',
            'package-builder' => 'حدد: هل الفرق بين الحزم واضح لعميل يقرأها أول مرة؟ أي حزمة ستبيع أكثر ولماذا؟ هل هناك حزمة ميتة يجب حذفها أو تعديلها؟',
            'promise-builder' => 'حدد: هل الوعد قابل للإثبات؟ هل هو مميز عن المنافسين؟ هل يمكن استخدامه كعنوان إعلان مباشرة؟ ما الذي يحدث إذا لم يتحقق الوعد؟',
            'funnel-builder' => 'حدد: أين أكبر تسريب في القمع (أي مرحلة تخسر فيها أكثر)؟ ما أول تحسين يمكن إجراؤه هذا الأسبوع؟ ما المقياس الأهم لكل مرحلة؟',
            'customer-journey' => 'حدد: أين أكبر نقطة احتكاك يتوقف عندها العملاء؟ ما لحظة "آها" التي يجب الوصول إليها بسرعة؟ كيف تحوّل عميلاً راضياً إلى مُحيل؟',
            'marketing-plan' => 'حدد: هل الخطة قابلة للتنفيذ هذا الأسبوع أم نظرية؟ ما أول 3 مهام محددة؟ ما المقياس الذي يثبت نجاح الخطة؟ ما خطة B إذا فشل المسار الأول؟',
            'content-plan' => 'حدد: هل المحتوى يخدم مرحلة معينة من القمع أم عشوائي؟ ما المحتوى الواحد الذي يجب إنتاجه أولاً؟ كيف تعيد استخدام كل قطعة محتوى 3 مرات؟',
            'campaign-builder' => 'حدد: هل الحملة لها هدف واحد واضح؟ ما العنصر الذي يجب اختباره أولاً (A/B)؟ ما ميزانية الاختبار المقترحة؟ متى يجب إيقاف الحملة إذا لم تنجح؟',
            'follow-up-sequence' => 'حدد: هل التسلسل يبني ثقة تدريجية أم يبيع من أول رسالة؟ ما التوقيت المثالي بين الرسائل؟ ما سبب التوقف الأكثر احتمالاً وكيف تمنعه؟',
            'kpi-tracker' => 'حدد: هل المؤشرات قائدة (تتنبأ بالمستقبل) أم تابعة (تصف الماضي)؟ ما المؤشر الواحد الذي إذا تحسّن سيحسّن كل شيء؟ ما إجراء الطوارئ عند الانخفاض؟',
            'execution-plan' => 'حدد: هل كل مهمة لها مسؤول ووقت محدد؟ ما أول عائق محتمل وكيف تتجنبه؟ ما نقطة المراجعة الأولى ومتى؟ ما الذي يمكن حذفه لتبسيط الخطة؟',
            'performance-review' => 'حدد: ما النمط المتكرر في النتائج (نجاح أو فشل)؟ ما السبب الجذري الحقيقي وليس الظاهري؟ ما القرار الواحد الذي يجب اتخاذه بناءً على هذه البيانات؟',
            'smart-recommendations' => 'حدد: ما الفعل الواحد الذي سيُحدث أكبر أثر بأقل موارد؟ ما الذي يجب التوقف عنه فوراً؟ ما الفرصة المخفية في البيانات الحالية؟',
            'growth-priorities' => 'حدد: ما مسار النمو الأقل مخاطرة والأسرع عائداً؟ ما شرط الانتقال للمسار التالي؟ ما الذي يجب تثبيته قبل التوسع؟ متى يجب التوقف عن هذا المسار؟',
            default => 'حدد: ما أهم نتيجة عملية؟ ما أكبر مخاطرة؟ ما أول خطوة يجب تنفيذها؟ ما الذي سيخسره إذا لم يتحرك؟',
        };
    }

    /**
     * @param  array<string, mixed>  $currentInputs
     * @param  array<string, array{label: string, answer_tip: string}>  $fieldLabelMap
     * @return array{success: bool, suggestions?: array<string, string>, next_step?: string, insights?: string, error?: string}
     */
    public function generateFieldSuggestions(
        string $toolCode,
        string $toolName,
        array $currentInputs,
        ?int $workspaceId = null,
        ?int $projectId = null,
        array $fieldLabelMap = [],
        ?string $toolOutcomeHint = null,
        ?string $modeLabel = null,
    ): array {
        $contextBlock = $this->contextBuilder->promptBlockForIds($workspaceId, $projectId);
        if (trim($contextBlock) === '') {
            $contextBlock = 'لا توجد بيانات سابقة لهذا المشروع.';
        }

        // حدّ أعلى للحقول الفارغة المقترَحة دفعةً واحدة: يمنع قصّ JSON عند الإخراج
        // الطويل (maxOutputTokens) ويبقي الاقتراحات مركّزة وقابلة للتحليل.
        $emptyKeys = collect($currentInputs)
            ->filter(fn ($v) => ! is_string($v) || trim($v) === '')
            ->keys()
            ->values()
            ->take(12)
            ->all();

        $outcomeLine = $toolOutcomeHint !== null && trim($toolOutcomeHint) !== ''
            ? 'غرض هذه الأداة: '.trim($toolOutcomeHint)
            : '';
        $modeLine = $modeLabel !== null && trim($modeLabel) !== ''
            ? 'وضع الإدخال الحالي: '.trim($modeLabel)
            : '';

        $filledBlock = collect($currentInputs)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(function ($v, $k) use ($fieldLabelMap) {
                $meta = $fieldLabelMap[$k] ?? null;
                $label = is_array($meta) ? ($meta['label'] ?? $k) : $k;

                return '- [`'.((string) $k).'`] '.((string) $label).': '.((string) $v);
            })
            ->implode("\n");

        if (trim($filledBlock) === '') {
            $filledBlock = '(لا توجد حقول مملوءة بعد)';
        }

        // الأهداف: الحقول الفارغة (إنشاء) + الممتلئة (تحسين/استبدال)، بأولوية الفارغة، بحدّ 12.
        $filledKeys = collect($currentInputs)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->keys()
            ->filter(fn ($k) => $k !== 'brief')
            ->values()
            ->all();
        $targetKeys = array_values(array_slice(
            array_merge($emptyKeys, array_values(array_diff($filledKeys, $emptyKeys))),
            0,
            12,
        ));

        if ($targetKeys === []) {
            return ['success' => false, 'error' => 'لا توجد حقول لاقتراح قيم لها.'];
        }

        $targetFieldsBlock = collect($targetKeys)->map(function (string $k) use ($fieldLabelMap, $currentInputs): string {
            $meta = $fieldLabelMap[$k] ?? null;
            $label = is_array($meta) ? ($meta['label'] ?? $k) : $k;
            $tip = is_array($meta) ? trim((string) ($meta['answer_tip'] ?? '')) : '';
            $cur = is_string($currentInputs[$k] ?? null) ? trim((string) $currentInputs[$k]) : '';
            $line = '- المفتاح الحرفي `'.$k.'` — السؤال: '.$label;
            if ($tip !== '') {
                $line .= ' — توجيه: '.$tip;
            }
            $line .= $cur !== ''
                ? "\n  الإجابة الحالية (حسّنها لتكون أدقّ وأقرب لمن يشتري؛ إن كانت ممتازة أبقها): ".$cur
                : "\n  (فارغ — اكتب إجابة قوية محدّدة)";

            return $line;
        })->implode("\n");

        $keysJsonList = implode('، ', array_map(fn (string $k): string => '"'.$k.'"', $targetKeys));

        $prompt = <<<PROMPT
        أنت مستشار تسويق واستراتيجية، بأسلوب قريب من المستخدم (بلغة «أنت») ودون حشو. مهمتك تعبئة وتحسين إجابات أداة "{$toolName}" (الكود التقني: {$toolCode}).

        {$contextBlock}

        {$outcomeLine}
        {$modeLine}

        ما هو مملوء الآن (للسياق):
        {$filledBlock}

        الحقول المطلوب اقتراح/تحسين قيمها (استخدم **المفتاح التقني** كما هو بين المزدوجين فقط):
        {$targetFieldsBlock}

        === قواعد صارمة ===
        1) أعد JSON فقط، بدون نص قبله أو بعده، بدون ```markdown
        2) مفاتيح كائن "suggestions" يجب أن تطابق **حرفياً** هذه المفاتيح فقط: {$keysJsonList}
        3) **ممنوع منعاً باتاً** إعادة صياغة السؤال أو نسخ التوجيه، وممنوع أن تبدأ القيمة بكلمة «مثل» أو «مثال»، وممنوع أن تحتوي علامة استفهام. اكتب **الإجابة المباشرة فقط** كأنك المستخدم يجيب.
        4) للحقل المملوء: أعد صياغته أقوى وأكثر تحديداً (فئة دقيقة، سلوك، مكان، رقم/زمن) وتخلّص من العموميات؛ للفارغ: اكتب إجابة قوية محدّدة
        5) لا تخترع أرقاماً أو أسماء عملاء أو نتائج لا يظهر أصلها في السياق أعلاه
        6) اجعل كل قيمة جملة أو جملتين عمليتين تناسب سؤال الحقل تحديداً، ولا تكرّر إجابة حقل آخر
        7) "insight": جملة أو جملتان تربط هذه الأداة بمرحلة المشروع أو الهدف الظاهر في السياق (اذكر اسم المشروع إن وُجد)

        مثال على الفرق:
        السؤال: «نجاح هدفك يعتمد على ماذا؟» — توجيه: «مثل بناء قاعدة عملاء».
        قيمة سيئة مرفوضة: «نجاح هدفك يعتمد على ماذا؟ مثل بناء قاعدة عملاء».
        قيمة جيدة: «الوصول إلى 50 عميلاً متكرراً خلال 90 يوماً بمعدل احتفاظ 60%».

        الصيغة:
        {
            "suggestions": {
                "...المفتاح_الحرفي_فقط...": "قيمة محدّدة ومحسّنة"
            },
            "insight": "ربط استراتيجي مختصر"
        }
        PROMPT;

        $text = $this->gateway->generateText($prompt);

        if (! $text) {
            return ['success' => false, 'error' => 'تعذر توليد الاقتراحات حالياً.'];
        }

        $parsed = $this->looseJsonDecode($text);

        if (! is_array($parsed)) {
            \Illuminate\Support\Facades\Log::warning('AI field suggestions: JSON parse failed', [
                'tool' => $toolCode,
                'raw' => mb_substr($text, 0, 600),
            ]);

            return ['success' => false, 'error' => 'تعذر تحليل الاقتراحات.'];
        }

        $rawSuggestions = $parsed['suggestions'] ?? [];
        $suggestions = [];
        if (is_array($rawSuggestions)) {
            foreach ($targetKeys as $key) {
                if (! isset($rawSuggestions[$key]) || ! is_string($rawSuggestions[$key])) {
                    continue;
                }
                $trimmed = trim($rawSuggestions[$key]);
                if ($trimmed === '') {
                    continue;
                }

                // رفض الاقتراح الغبي: إعادة نص السؤال أو التوجيه (مثل ...) بدل إجابة فعلية.
                $meta = $fieldLabelMap[$key] ?? null;
                $label = is_array($meta) ? (string) ($meta['label'] ?? '') : '';
                $tip = is_array($meta) ? (string) ($meta['answer_tip'] ?? '') : '';
                if ($this->isEchoSuggestion($trimmed, $label, $tip)) {
                    continue;
                }

                $suggestions[$key] = $trimmed;
            }
        }

        return [
            'success' => true,
            'suggestions' => $suggestions,
            'insight' => is_string($parsed['insight'] ?? null) ? trim((string) $parsed['insight']) : '',
        ];
    }

    /**
     * صقل الحكم والملاحظة الاستراتيجية نصياً عبر LLM مع الإبقاء على الأرقام كما أخرجها المحرك المنظم.
     *
     * @param  array<string, mixed>  $assessment
     * @param  array<string, array{label: string, answer_tip: string}>  $fieldLabelMap
     * @return array{verdict: string, strategic_note: string}|null
     */
    public function enrichToolAssessmentNarrative(
        array $assessment,
        string $toolCode,
        string $toolName,
        array $inputs,
        array $fieldLabelMap,
        ?int $workspaceId,
        ?int $projectId,
    ): ?array {
        if (! config('services.ai_tool_assessment.enrich_narrative_with_llm', true)) {
            return null;
        }

        if (! $this->isAiGatewayConfigured()) {
            return null;
        }

        $contextBlock = $this->contextBuilder->promptBlockForIds($workspaceId, $projectId);
        if (trim($contextBlock) === '') {
            $contextBlock = 'لا توجد بيانات سابقة مفصّلة.';
        }

        $toolFocus = $this->toolAnalysisFocus($toolCode);

        $labeled = collect($inputs)
            ->filter(fn ($_, $k) => $k !== 'brief')
            ->map(function ($v, $k) use ($fieldLabelMap) {
                $key = (string) $k;
                $label = $fieldLabelMap[$key]['label'] ?? $key;
                $text = is_string($v) ? trim($v) : '';
                if ($text === '') {
                    return '- [`'.$key.'`] '.$label.': (فارغ)';
                }

                return '- [`'.$key.'`] '.$label.': '.Str::limit($text, 220, '…');
            })
            ->values()
            ->implode("\n");

        if ($labeled === '') {
            $labeled = '(لا مدخلات بعد)';
        }

        $dims = collect($assessment['dimensions'] ?? [])
            ->take(4)
            ->map(fn ($d) => (($d['label'] ?? '').': '.((string) ($d['score'] ?? 0)).'% — '.((string) ($d['note'] ?? ''))))
            ->implode("\n");

        $stList = collect($assessment['strengths'] ?? [])->take(4)->filter(fn ($s) => is_string($s) && trim($s) !== '');
        $strengthsBlock = $stList->isEmpty() ? '(لا يوجد)' : '- '.$stList->implode("\n- ");

        $gapList = collect($assessment['gaps'] ?? [])->take(4)->filter(fn ($s) => is_string($s) && trim($s) !== '');
        $gapsBlock = $gapList->isEmpty() ? '(لا يوجد)' : '- '.$gapList->implode("\n- ");

        $recList = collect($assessment['recommendations'] ?? [])->take(4)->filter(fn ($s) => is_string($s) && trim($s) !== '');
        $recsBlock = $recList->isEmpty() ? '(لا يوجد)' : '- '.$recList->implode("\n- ");

        $prevVerdict = (string) ($assessment['verdict'] ?? '');
        $prevNote = (string) ($assessment['strategic_note'] ?? '');
        $score = (int) ($assessment['score'] ?? 0);

        $prompt = implode("\n", [
            'أداة: «'.$toolName.'» ('.$toolCode.')',
            'درجة الجودة الإجمالية المعتمدة (لا تغيّرها ولا تنازعها): '.$score.'/100',
            '',
            '=== السياق ===',
            $contextBlock,
            '',
            '=== تركيز تحليل هذه الأداة ===',
            $toolFocus,
            '',
            '=== المدخلات (عناوين حقول واضحة) ===',
            $labeled,
            '',
            '=== أبعاد التقييم (مرجعية) ===',
            $dims !== '' ? $dims : '(غير متوفر)',
            '',
            '=== نقاط قوة (مرجعية) ===',
            $strengthsBlock,
            '',
            '=== فجوات (مرجعية) ===',
            $gapsBlock,
            '',
            '=== توصيات منظمة (مرجعية) ===',
            $recsBlock,
            '',
            '=== مسودة نصية حالية ===',
            'verdict: '.$prevVerdict,
            'strategic_note: '.$prevNote,
            '',
            'المطلوب: أعد صياغة verdict و strategic_note بأسلوب مستشار قريب ومفيد (عربية واضحة)، من دون تناقض مع الدرجة '.$score.'، ومن دون اختراع أرقام أو عملاء أو نتائج.',
            'الحدود: verdict جملتان كحد أقصى؛ strategic_note جملتان كحد أقصى.',
            '',
            'أعد JSON فقط بهذا الشكل:',
            '{"verdict":"...","strategic_note":"..."}',
        ]);

        $system = 'أنت محرر استراتيجي. التزم بالحقائق الواردة فقط. لا تغيّر أي رقماً للتقييم. أعد JSON صالحاً فقط بدون أي نص حوله.';

        $text = $this->gateway->generateText($prompt, $system);

        if (! $text) {
            return null;
        }

        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        if (! is_array($parsed)) {
            return null;
        }

        $verdict = trim((string) ($parsed['verdict'] ?? ''));
        $strategicNote = trim((string) ($parsed['strategic_note'] ?? ''));

        if ($verdict === '' || $strategicNote === '') {
            return null;
        }

        return [
            'verdict' => Str::limit($verdict, 420, '…'),
            'strategic_note' => Str::limit($strategicNote, 520, '…'),
        ];
    }

    /**
     * يكشف الاقتراح "الغبي" الذي يعيد صياغة السؤال أو يبدأ بمثال التوجيه بدل إجابة فعلية.
     */
    private function isEchoSuggestion(string $suggestion, string $label, string $tip): bool
    {
        $s = $this->normalizeForCompare($suggestion);
        $l = $this->normalizeForCompare($label);

        if ($s === '') {
            return true;
        }

        // يبدأ بمثال أو "مثل ..." = نسخ التوجيه لا إجابة.
        if (preg_match('/^(?:مثل|مثال|على سبيل المثال)\b/u', trim($suggestion)) === 1) {
            return true;
        }

        // يطابق السؤال أو يبدأ به (إعادة صياغة السؤال).
        if ($l !== '' && mb_strlen($l) >= 6 && ($s === $l || str_starts_with($s, $l))) {
            return true;
        }

        // يحتوي نص السؤال كاملاً + علامة استفهام (السؤال مدسوس داخل "الإجابة").
        if ($l !== '' && mb_strlen($l) >= 6 && str_contains($s, $l) && str_contains($suggestion, '؟')) {
            return true;
        }

        // يطابق مثال التوجيه نفسه (answer_tip) دون إضافة.
        $tipExample = trim((string) preg_replace('/^.*?مثل\s*:?\s*/u', '', $tip));
        if ($tipExample !== '' && mb_strlen($tipExample) >= 6 && $this->normalizeForCompare($tipExample) === $s) {
            return true;
        }

        return false;
    }

    private function normalizeForCompare(string $text): string
    {
        $t = mb_strtolower(trim($text));

        return trim((string) preg_replace('/[\s؟?.،,:!]+/u', ' ', $t));
    }

    /**
     * فكّ JSON متين من ردّ LLM: يزيل أسوار ```، ويستخرج الكائن من أول { إلى آخر }
     * إن أحاط النموذج الـ JSON بنصّ. يعالج السبب الأشيع لـ "تعذر تحليل الاقتراحات".
     *
     * @return array<mixed>|null
     */
    private function looseJsonDecode(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $cleaned = trim($text);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*```$/', '', $cleaned) ?? $cleaned;

        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // النموذج أحاط الـ JSON بنصّ — استخرج من أول { إلى آخر }.
        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($cleaned, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function isAiGatewayConfigured(): bool
    {
        return (bool) (config('services.gemini.key') || config('services.nvidia.key'));
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function extractAssistantTextFromGatewayResponse(?array $response): ?string
    {
        if ($response === null) {
            return null;
        }

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $t = $response['candidates'][0]['content']['parts'][0]['text'];

            return is_string($t) ? $t : null;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

        return is_string($content) ? $content : null;
    }

    /**
     * @param  array<string, mixed>  $sourceContext
     */
    private function buildGenerationContextBlock(?int $workspaceId, ?int $projectId, array $sourceContext = []): string
    {
        $blocks = array_filter([
            $this->contextBuilder->promptBlockForIds($workspaceId, $projectId),
            $this->formatSourceContextBlock($sourceContext),
        ]);

        return implode("\n\n", $blocks);
    }

    /**
     * @param  array<string, mixed>  $sourceContext
     */
    private function formatSourceContextBlock(array $sourceContext): string
    {
        $parts = [];

        $profile = $sourceContext['workspace_profile'] ?? [];
        if (is_array($profile)) {
            $profileLine = collect([
                'persona' => $profile['persona'] ?? null,
                'primary_goal' => $profile['primary_goal'] ?? null,
                'audience' => $profile['audience'] ?? null,
                'country' => $profile['country'] ?? null,
                'content_locale' => $profile['content_locale'] ?? null,
            ])->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value, string $key): string => $key.': '.$value)
                ->implode(' | ');

            if ($profileLine !== '') {
                $parts[] = 'ملف المساحة الحالي: '.$profileLine;
            }
        }

        $project = $sourceContext['project'] ?? [];
        if (is_array($project)) {
            $projectLine = collect([
                'name' => $project['name'] ?? null,
                'stage' => isset($project['stage']) ? (string) $project['stage'] : null,
                'status' => $project['status'] ?? null,
            ])->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value, string $key): string => $key.': '.$value)
                ->implode(' | ');

            if ($projectLine !== '') {
                $parts[] = 'بيانات المشروع الجاري: '.$projectLine;
            }
        }

        $client = $sourceContext['client'] ?? [];
        if (is_array($client) && is_string($client['name'] ?? null) && trim($client['name']) !== '') {
            $parts[] = 'العميل المرتبط: '.$client['name'];
        }

        $playbook = $sourceContext['playbook'] ?? [];
        if (is_array($playbook) && ! empty($playbook['principles'])) {
            $pbLines = ['دليل خبرة مقطّر لهذه الأداة (استرشد به):'];
            foreach (array_slice((array) $playbook['principles'], 0, 3) as $principle) {
                $pbLines[] = '- مبدأ: '.(string) $principle;
            }
            if (! empty($playbook['quick_win'])) {
                $pbLines[] = '- أسرع مكسب: '.(string) $playbook['quick_win'];
            }
            $parts[] = implode("\n", $pbLines);
        }

        $lessons = $sourceContext['lessons'] ?? [];
        if (is_array($lessons) && $lessons !== []) {
            $lessonLines = ['دروس متعلَّمة لهذه الأداة (التزم بها لرفع الجودة):'];
            foreach (array_slice($lessons, 0, 3) as $lesson) {
                if (is_string($lesson) && trim($lesson) !== '') {
                    $lessonLines[] = '- '.trim($lesson);
                }
            }
            if (count($lessonLines) > 1) {
                $parts[] = implode("\n", $lessonLines);
            }
        }

        $web = $sourceContext['web_signals'] ?? [];
        if (is_array($web) && ! empty($web['findings'])) {
            $lines = ['إشارات حيّة من الإنترنت (استخدمها كمرجع واقعي، ولا تخترع أرقاماً):'];
            if (! empty($web['summary'])) {
                $lines[] = '- '.$web['summary'];
            }
            foreach (array_slice((array) $web['findings'], 0, 3) as $finding) {
                $title = trim((string) ($finding['title'] ?? ''));
                $snippet = trim((string) ($finding['snippet'] ?? ''));
                if ($title !== '') {
                    $lines[] = '- ['.($finding['category'] ?? 'عام').'] '.$title.($snippet !== '' ? ' — '.$snippet : '');
                }
            }
            $parts[] = implode("\n", $lines);
        }

        return $parts === []
            ? ''
            : "=== سياق العملية الحالية ===\n".implode("\n", $parts);
    }
}
