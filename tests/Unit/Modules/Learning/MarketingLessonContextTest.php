<?php

namespace Tests\Unit\Modules\Learning;

use App\Models\Content;
use App\Models\MarketingLearningRun;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Learning\MarketingLessonAssistant;
use App\Modules\Learning\MarketingLessonContextBuilder;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLessonContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_contains_the_full_lesson_exact_question_and_adjacent_lessons(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-03')->sole();
        $lesson->update(['body_html' => '<section id="customer-proof"><h2>اختبار العميل</h2><p>علامة سياق كاملة لا يجوز حذفها.</p></section>']);
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        $run = MarketingLearningRun::startForWorkspace($workspace, $user);
        $attempt = $run->attemptFor('describe-real-customer');

        $context = app(MarketingLessonContextBuilder::class)->build(
            $attempt,
            'customer_profile',
            'customer-proof',
        );

        $this->assertStringContainsString('علامة سياق كاملة لا يجوز حذفها', $context['lesson']['full_text']);
        $this->assertSame('من هو العميل الذي تريد خدمته الآن؟', $context['question']['label']);
        $this->assertStringContainsString('يحدد وضع العميل', $context['question']['rubric']);
        $this->assertSame('customer-proof', $context['active_section']['id']);
        $this->assertNotEmpty($context['related_lessons']['previous']);
        $this->assertNotEmpty($context['related_lessons']['next']);
    }

    public function test_assistant_prompt_requires_field_specific_help_and_includes_full_page_context(): void
    {
        $captured = new \stdClass;
        $runner = new class($captured) extends StructuredRunner
        {
            public function __construct(private \stdClass $captured) {}

            public function run(AIRequest $request, ?ToolRun $toolRun = null): array
            {
                $this->captured->request = $request;

                return [
                    'field_help' => 'حدد وضع العميل وسلوكه في هذا الحقل.',
                    'example' => 'صاحب متجر يدير الطلبات بنفسه.',
                    'why_it_fits' => 'يرتبط بمفهوم العميل الحقيقي في الدرس.',
                    'next_action' => 'اكتب موقفًا واحدًا حدث فعلًا.',
                    'basis' => ['نص الدرس', 'معيار السؤال'],
                ];
            }
        };
        $assistant = new MarketingLessonAssistant($runner);
        $result = $assistant->suggest([
            'lesson' => ['title' => 'اعرف عميلك الحقيقي', 'full_text' => 'علامة الصفحة الكاملة'],
            'exercise' => ['title' => 'صف عميلك الحقيقي', 'purpose' => 'وصف عملي'],
            'question' => ['label' => 'من هو العميل؟', 'rubric' => 'وصف سلوكي دقيق'],
            'active_section' => ['id' => 'customer', 'title' => 'العميل الحقيقي', 'text' => 'تفصيل القسم'],
            'related_lessons' => [],
            'answers' => [],
            'project' => null,
        ]);

        $prompt = collect($captured->request->messages)->pluck('content')->implode("\n");
        $this->assertStringContainsString('علامة الصفحة الكاملة', $prompt);
        $this->assertStringContainsString('من هو العميل؟', $prompt);
        $this->assertStringContainsString('امنع النصائح العامة', $prompt);
        $this->assertSame('فرضية', $result['evidence_label']);
    }
}
