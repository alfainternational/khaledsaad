<?php

namespace Tests\Feature;

use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\User;
use App\Services\Growth\GrowthSchemas;
use App\Services\Growth\SyntheticAudience;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مختبر الجمهور يخرج بنصٍّ لكل شخصية لا بنصٍّ واحد للجميع.
 *
 * اعتراض المتردد نقيض اعتراض الحسّاس للسعر، فرسالة ترضيهما معًا لا تُقنع
 * أيًّا منهما. هذه الاختبارات تحرس ذلك من جهتين: العقد المرسل إلى النموذج،
 * وما يُخزَّن فعلًا بعد التشغيل.
 */
class PersonaMessageTailoringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_contract_demands_a_message_per_persona_and_forbids_a_unified_one(): void
    {
        $schema = GrowthSchemas::personaTest();
        $reaction = $schema['properties']['reactions']['items'];

        $this->assertContains('tailored_message', $reaction['required']);
        $this->assertContains('angle', $reaction['required']);

        // نصٌّ واحد «محسّن للجميع» هو عين ما يكسر التخصيص — فلا مكان له في العقد.
        $this->assertArrayNotHasKey('improved_version', $schema['properties']['overall']['properties']);
        $this->assertNotContains('improved_version', $schema['properties']['overall']['required']);

        // الخلاصة تصف الفروق والخطر فقط.
        $this->assertSame(['verdict', 'biggest_risk'], $schema['properties']['overall']['required']);
    }

    #[Test]
    public function each_persona_keeps_its_own_message_and_the_prompt_bans_a_shared_one(): void
    {
        $panel = $this->panel();
        $captured = null;

        $this->app->instance(StructuredRunner::class, new class($captured) extends StructuredRunner
        {
            public function __construct(public mixed &$request) {}

            public function run(AIRequest $request, $toolRun = null): array
            {
                $this->request = $request;

                return [
                    'reactions' => [
                        [
                            'persona' => 'المتردد الحذر',
                            'score' => 41,
                            'reaction' => 'وعد كبير بلا دليل — سمعت مثله كثيرًا ولم يتغيّر شيء.',
                            'objection' => 'ما الذي يثبت أن هذا مختلف؟',
                            'angle' => 'الدليل قبل الوعد لأن ثقته مكسورة من تجارب سابقة.',
                            'tailored_message' => 'ثلاثة مطاعم في الرياض رفعت طلباتها ٣٠٪ خلال شهرين. اقرأ أرقامهم قبل أن تصدّقنا.',
                        ],
                        [
                            'persona' => 'الحساس للسعر',
                            'score' => 58,
                            'reaction' => 'يبدو جيدًا لكن لا أعرف كم سيكلفني ولا ما البديل.',
                            'objection' => 'كم سيكلفني هذا فعلًا؟',
                            'angle' => 'الرقم صراحةً ومقارنته بتكلفة التأجيل يزيلان قلق الميزانية.',
                            'tailored_message' => 'ابدأ بـ٤٩ ريالًا شهريًا. أقل من فاتورة إعلان واحد فاشل، وتعرف نتيجتك قبل أن تدفع أكثر.',
                        ],
                    ],
                    'overall' => [
                        'verdict' => 'الرسالة تخاطب من يثق أصلًا، وتخسر من يحتاج دليلًا أو رقمًا.',
                        'biggest_risk' => 'غياب الدليل يجعل الوعد يشبه وعود المنافسين.',
                    ],
                ];
            }
        });

        $test = app(SyntheticAudience::class)->test(
            $panel,
            'نساعدك على مضاعفة مبيعاتك بالتسويق الذكي.',
            User::first() ?? User::factory()->create(),
        );

        $messages = array_column($test->results['reactions'], 'tailored_message');

        $this->assertCount(2, $messages);
        $this->assertCount(2, array_unique($messages), 'كل شخصية يجب أن تخرج بنصّها هي، لا بنسخة مشتركة.');
        $this->assertArrayNotHasKey('improved_version', $test->results['overall']);

        // الزاوية التعليمية تبقى خارج نص الرسالة حتى لا تُنسخ معها إلى الإعلان.
        foreach ($test->results['reactions'] as $reaction) {
            $this->assertStringNotContainsString($reaction['angle'], $reaction['tailored_message']);
        }

        $prompt = $this->app->make(StructuredRunner::class)->request->messages[0]['content'];
        $this->assertStringContainsString('ممنوع', $prompt);
        $this->assertStringContainsString('نسخة موحّدة', $prompt);
    }

    private function panel(): PersonaPanel
    {
        $user = User::factory()->create();

        $project = Project::create([
            'workspace_id' => $user->primaryWorkspace()->id,
            'name' => 'مطعم تجريبي',
            'slug' => 'persona-message-project',
            'industry' => 'مطاعم',
            'stage' => 'growth',
            'status' => 'active',
        ]);

        return PersonaPanel::create([
            'project_id' => $project->id,
            'personas' => [
                ['name' => 'المتردد الحذر', 'age_range' => '30-45', 'role' => 'صاحب مطعم',
                    'pains' => ['خيبات سابقة'], 'buying_style' => 'يحتاج دليلًا', 'quote' => 'أرني نتيجة.'],
                ['name' => 'الحساس للسعر', 'age_range' => '22-40', 'role' => 'صاحب كافيه',
                    'pains' => ['ميزانية ضيقة'], 'buying_style' => 'يوازن بدقة', 'quote' => 'كم سيكلفني؟'],
            ],
            'source' => 'rules',
            'generated_at' => now(),
        ]);
    }
}
