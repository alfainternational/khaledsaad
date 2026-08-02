<?php

namespace App\Services\Growth;

use App\Models\PersonaPanel;
use App\Models\PersonaTest;
use App\Models\Project;
use App\Models\User;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Throwable;

/**
 * الجمهور الاصطناعي: بديل مجموعة التركيز لمن لا يملك ميزانيتها.
 *
 * تُبنى لوحة شخصيات من ملف المشروع وجمهوره مرة واحدة، ثم تُختبر عليها
 * الرسائل قبل الإنفاق: كل شخصية تعطي درجة قبول واعتراضها الرئيسي،
 * ثم رسالتها هي — نصًّا مستقلًّا يعالج اعتراضها وحدها.
 *
 * الشخصية وحدة المخرج لا الرسالة: «نسخة محسّنة» واحدة للجميع تجمع
 * اعتراضات متناقضة فلا تُقنع أحدًا. المستخدم يخرج بعدد نصوص بعدد شخصياته.
 */
class SyntheticAudience
{
    public function __construct(private readonly StructuredRunner $runner) {}

    public function buildPanel(Project $project): PersonaPanel
    {
        [$personas, $source] = $this->personas($project);

        return PersonaPanel::updateOrCreate(
            ['project_id' => $project->id],
            [
                'personas' => $personas,
                'source' => $source,
                'generated_at' => now(),
            ],
        );
    }

    /**
     * اختبار رسالة على اللوحة. يرمي استثناء NA إن فشل النموذج نهائيًا —
     * لا أرضية حتمية هنا: رد فعل مُخترع قالبيًا أسوأ من قول «تعذّر».
     */
    public function test(PersonaPanel $panel, string $message, User $user): PersonaTest
    {
        $results = $this->runner->run(AIRequest::json(
            messages: [
                ['role' => 'system', 'content' => implode("\n", [
                    'أنت تدير جلسة مجموعة تركيز افتراضية. تتقمص كل شخصية معطاة، تقيّم رسالة تسويقية بلسانها، ثم تكتب لها رسالتها هي.',
                    'القواعد:',
                    '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                    '2. تقمّص كل شخصية بصدق: بعمرها ودورها وأوجاعها وأسلوب شرائها — لا مجاملة لصاحب الرسالة.',
                    '3. الدرجة من 100 تعكس احتمال أن تتفاعل هذه الشخصية فعلًا مع الرسالة.',
                    '4. الاعتراض هو الجملة التي ستقولها الشخصية لنفسها قبل أن تتجاهل الرسالة.',
                    '5. tailored_message: رسالة مستقلة لهذه الشخصية وحدها، تعالج دافعًا واحدًا واعتراضًا واحدًا، بمفردات تفهمها، وبطول الرسالة الأصلية أو أقصر — نصٌّ جاهز للنشر لا وصف لما ينبغي كتابته.',
                    '6. ممنوع أن تصلح رسالة شخصية لغيرها، وممنوع جمع مزايا كل الشخصيات في نص واحد، وممنوع إنتاج نسخة موحّدة «محسّنة للجميع».',
                    '7. angle: جملة واحدة تشرح لماذا تُقنع هذه الصياغة هذه الشخصية تحديدًا. تبقى خارج نص الرسالة ولا تُنسخ معها.',
                    '8. حافظ على صوت العلامة كما يظهر في الرسالة الأصلية: يتغيّر ما يُقال ولمن، لا شخصية العلامة.',
                    '9. الخلاصة تصف الفرق بين الشخصيات وأكبر خطر — ولا تكتب فيها رسالة.',
                ])],
                ['role' => 'user', 'content' => implode("\n\n", [
                    'الشخصيات: '.json_encode($panel->personas, JSON_UNESCAPED_UNICODE),
                    "الرسالة المطلوب اختبارها:\n{$message}",
                ])],
            ],
            schema: GrowthSchemas::personaTest(),
            tier: 'standard',
            stage: 'persona_test',
            salvage: true,
        ));

        return PersonaTest::create([
            'persona_panel_id' => $panel->id,
            'user_id' => $user->id,
            'message' => $message,
            'results' => $results,
        ]);
    }

    /**
     * الشخصيات: النموذج أولًا، وإن تعذّر تُشتق لوحة مبدئية من شرائح
     * الجمهور التي أدخلها المستخدم — لوحة أفقر لكنها ليست فراغًا.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function personas(Project $project): array
    {
        $project->loadMissing(['profile', 'audiences']);

        $context = [
            'project' => $project->only(['name', 'industry', 'stage']),
            'profile' => $project->profile?->only([
                'business_model', 'description', 'geography',
                'primary_goal', 'value_proposition', 'channels',
            ]) ?? [],
            'audiences' => $project->audiences
                ->map(fn ($audience) => $audience->only(['name', 'pains', 'gains', 'behaviors']))
                ->all(),
        ];

        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => implode("\n", [
                        'أنت باحث سوق عربي تبني شخصيات عملاء (Personas) واقعية لاختبار الرسائل التسويقية.',
                        'القواعد:',
                        '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                        '2. الشخصيات من واقع السوق والجمهور المعطى: أسماء عربية وأدوار وأوجاع يصدقها صاحب المشروع فورًا.',
                        '3. نوّع الشخصيات: المتحمس، المتردد، الحساس للسعر — لا أربع نسخ من شخص واحد.',
                        '4. quote: جملة تقولها الشخصية عن مشكلتها بلسانها اليومي.',
                    ])],
                    ['role' => 'user', 'content' => 'بيانات المشروع: '.json_encode($context, JSON_UNESCAPED_UNICODE)],
                ],
                schema: GrowthSchemas::personaPanel(),
                tier: 'standard',
                stage: 'persona_panel',
                salvage: true,
            ));

            return [$payload['personas'], 'ai'];
        } catch (Throwable) {
            return [$this->fallbackPersonas($project), 'rules'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackPersonas(Project $project): array
    {
        $audiences = $project->audiences;

        if ($audiences->isNotEmpty()) {
            return $audiences->take(4)->map(fn ($audience) => [
                'name' => $audience->name,
                'age_range' => 'غير محدد',
                'role' => $audience->name,
                'pains' => array_values(array_filter(array_map('trim', explode('،', (string) $audience->pains)))),
                'buying_style' => (string) $audience->behaviors ?: 'غير محدد',
                'quote' => 'أبحث عن حل يفهم وضعي قبل أن يبيعني.',
            ])->values()->all();
        }

        // لا شرائح جمهور بعد: ثلاث زوايا نظر عامة تكفي لاختبار أولي.
        return [
            [
                'name' => 'المتحمس المستعجل',
                'age_range' => '25-35',
                'role' => 'عميل جاهز للشراء يقارن الخيارات',
                'pains' => ['ضيق الوقت', 'كثرة الخيارات المتشابهة'],
                'buying_style' => 'يقرر سريعًا إذا وضحت القيمة',
                'quote' => 'قل لي مباشرة: ماذا سأستفيد ولماذا أنت تحديدًا؟',
            ],
            [
                'name' => 'المتردد الحذر',
                'age_range' => '30-45',
                'role' => 'عميل جُرّب عليه الكثير من الوعود',
                'pains' => ['خيبات سابقة مع وعود تسويقية', 'الخوف من إهدار المال'],
                'buying_style' => 'يحتاج دليلًا وتجارب آخرين قبل أي التزام',
                'quote' => 'كلهم يقولون نفس الكلام — أرني نتيجة حقيقية.',
            ],
            [
                'name' => 'الحساس للسعر',
                'age_range' => '22-40',
                'role' => 'عميل ميزانيته محدودة ويوازن بدقة',
                'pains' => ['الميزانية الضيقة', 'صعوبة تبرير المصروف'],
                'buying_style' => 'يشتري الأرخص إلا إذا فهم فرق القيمة',
                'quote' => 'ما الذي يجعل هذا يستحق الفرق في السعر؟',
            ],
        ];
    }
}
