<?php

namespace Database\Seeders;

use App\Models\PromptVersion;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ToolCatalogSeeder extends Seeder
{
    /**
     * الأدوات المعروضة وغير المشغَّلة في هذا الإصدار.
     * تُعرض بحالة صريحة «قريبًا» بدل واجهة معطلة صامتة.
     *
     * @var array<int, array<string, mixed>>
     */
    private const COMING_SOON = [
        ['key' => 'brand-clarity', 'name' => 'Brand Clarity', 'title' => 'الناس لا تفهم ماذا تقدّم بالضبط',
            'description' => 'نصيغ معك جملة واحدة يفهم منها العميل ماذا تبيع ولماذا يشتري منك.',
            'pain' => 'تشرح مشروعك ويظل الناس يسألون: طيب أنت تعمل ماذا بالضبط؟',
            'promise' => 'جملة واضحة تستخدمها في كل مكان، ويفهمها عميلك من أول مرة.',
            'category' => 'وضوح العرض', 'sort_order' => 2],

        ['key' => 'audience-map', 'name' => 'Audience Map', 'title' => 'لا تعرف بالضبط من تخاطب',
            'description' => 'نحدد معك من هو عميلك، وما الذي يهمه، وما الذي يوقفه عن الشراء.',
            'pain' => 'تسوّق للجميع، فتصرف كثيرًا ويأتيك عملاء غير مناسبين.',
            'promise' => 'صورة واضحة لعميلك: من هو، وأين تجده، وبأي كلام يقتنع.',
            'category' => 'عميلك', 'sort_order' => 3],

        ['key' => 'competitor-lens', 'name' => 'Competitor Lens', 'title' => 'لماذا يذهب الناس إلى منافسك؟',
            'description' => 'نقرأ معك ما يفعله منافسوك، ونجد المساحة التي تقدر تتميز فيها.',
            'pain' => 'تشوف منافسك ينمو ولا تعرف ما الذي يفعله ولا تملكه أنت.',
            'promise' => 'تعرف أين هم أقوى، وأين الفراغ الذي تستطيع أن تملأه أنت.',
            'category' => 'منافسوك', 'sort_order' => 4],

        ['key' => 'offer-builder', 'name' => 'Offer Builder', 'title' => 'عرضك لا يقنع بما يكفي',
            'description' => 'نعيد ترتيب عرضك: ما تقدمه، وسعره، وما يطمئن العميل قبل الدفع.',
            'pain' => 'الناس تسأل عن السعر ثم تختفي، وتشعر أن عرضك لا يقنع.',
            'promise' => 'عرض واضح يقلل تردد العميل ويسهّل عليه قرار الشراء.',
            'category' => 'عرضك', 'sort_order' => 5],

        ['key' => 'channel-fit', 'name' => 'Channel Fit', 'title' => 'لا تعرف أين تركّز جهدك',
            'description' => 'نحدد المكان الذي يوجد فيه عميلك ويناسب وقتك وميزانيتك.',
            'pain' => 'موجود في كل مكان بجهد قليل، فلا تنجح في أي مكان.',
            'promise' => 'منصة أو منصتان تركز فيهما، وسبب واضح لاختيارهما.',
            'category' => 'أين تسوّق', 'sort_order' => 7],

        ['key' => 'seo-compass', 'name' => 'SEO Compass', 'title' => 'لا يجدونك حين يبحثون في جوجل',
            'description' => 'نعرف ما الذي يبحث عنه عملاؤك، ونرتب لك ما تحتاج عمله ليجدوك.',
            'pain' => 'الناس تبحث عن خدمتك وتصل لغيرك، وأنت غير ظاهر.',
            'promise' => 'قائمة واضحة بما يبحث عنه عميلك، وخطوات ظهورك أمامه.',
            'category' => 'الظهور في البحث', 'sort_order' => 8],

        ['key' => 'agency-brief', 'name' => 'Agency Brief', 'title' => 'تريد تسليم التنفيذ لشخص أو وكالة',
            'description' => 'نجهّز ملفًا واضحًا تسلّمه لموظفك أو لمستقل أو لوكالة فيعرفوا المطلوب.',
            'pain' => 'كل مرة تشرح مشروعك من الأول، وتخرج النتيجة غير ما تتوقع.',
            'promise' => 'ملف مكتوب: المطلوب، والميزانية، وكيف نحكم أن العمل نجح.',
            'category' => 'التنفيذ مع غيرك', 'sort_order' => 11],
    ];

    public function run(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions as $definition) {
            $this->syncTool($definition);
        }

        $built = collect($definitions)->pluck('key')->all();

        foreach (self::COMING_SOON as $tool) {
            // الأداة التي صار لها ملف تعريف كامل لم تعد «قريبًا».
            // التخطي التلقائي يمنع إعادتها إلى الحالة القديمة عند كل بذر.
            if (in_array($tool['key'], $built, true)) {
                continue;
            }

            Tool::updateOrCreate(
                ['key' => $tool['key']],
                [...$tool, 'status' => Tool::STATUS_COMING_SOON],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return collect(glob(database_path('data/tools/*.php')))
            ->map(fn (string $path) => require $path)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function syncTool(array $definition): void
    {
        DB::transaction(function () use ($definition): void {
            $tool = Tool::updateOrCreate(
                ['key' => $definition['key']],
                [
                    'name' => $definition['name'],
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'pain' => $definition['pain'] ?? null,
                    'promise' => $definition['promise'] ?? null,
                    'audience' => $definition['audience'] ?? null,
                    'duration_minutes' => $definition['duration_minutes'] ?? null,
                    'category' => $definition['category'],
                    'sort_order' => $definition['sort_order'],
                    'status' => $definition['status'] ?? Tool::STATUS_COMING_SOON,
                ],
            );

            $version = ToolVersion::updateOrCreate(
                ['tool_id' => $tool->id, 'version' => 1],
                [
                    'credit_cost' => $definition['version']['credit_cost'],
                    'status' => 'published',
                    'output_schema' => $definition['version']['output_schema'],
                    'scoring_rules' => $definition['version']['scoring_rules'],
                    'section_plan' => $definition['version']['section_plan'],
                    'published_at' => now(),
                ],
            );

            $this->syncFields($version, $definition['fields']);
            $this->syncPrompts($version, $definition['prompts']);

            $tool->forceFill(['current_version_id' => $version->id])->save();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function syncFields(ToolVersion $version, array $fields): void
    {
        $keys = [];

        foreach ($fields as $index => $field) {
            $keys[] = $field['key'];

            ToolField::updateOrCreate(
                ['tool_version_id' => $version->id, 'key' => $field['key']],
                [
                    'label' => $field['label'],
                    'help' => $field['help'] ?? null,
                    'why' => $field['why'] ?? null,
                    'example' => $field['example'] ?? null,
                    'type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'validation' => $field['validation'] ?? null,
                    'required' => $field['required'] ?? true,
                    'step' => $field['step'],
                    'step_title' => $field['step_title'] ?? null,
                    'sort_order' => $index,
                    'visible_when' => $field['visible_when'] ?? null,
                    'profile_key' => $field['profile_key'] ?? null,
                ],
            );
        }

        $version->fields()->whereNotIn('key', $keys)->delete();
    }

    /**
     * @param  array<string, string>  $prompts
     */
    private function syncPrompts(ToolVersion $version, array $prompts): void
    {
        foreach ($prompts as $stage => $content) {
            $existing = PromptVersion::firstWhere([
                'tool_version_id' => $version->id,
                'stage' => $stage,
            ]);

            // BR-012: برومبت مقفل لا يُلمس. تغييره يستوجب إصدار أداة جديدًا،
            // ولا يجوز أن يتم بصمت من خلال إعادة تشغيل بذرة.
            if ($existing?->locked_at !== null) {
                continue;
            }

            PromptVersion::updateOrCreate(
                ['tool_version_id' => $version->id, 'stage' => $stage],
                ['content' => trim($content), 'status' => 'published', 'tier' => 'standard'],
            );
        }
    }
}
