<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ToolRun;
use App\Models\User;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLessonUpdateDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_lesson_update_is_saved_as_an_evidenced_draft_and_never_auto_published(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-07')->sole();
        $original = $lesson->body_html;
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->primaryWorkspace()->forceFill(['monthly_query_limit' => 10])->save();
        $this->app->instance(StructuredRunner::class, new class extends StructuredRunner
        {
            public function __construct() {}

            public function run(AIRequest $request, ?ToolRun $toolRun = null): array
            {
                return [
                    'summary' => 'تحديث أمثلة اختيار القناة مع الحفاظ على منهج الدرس.',
                    'proposed_body_html' => '<section><h2>اختيار القناة</h2><p>مسودة محدثة للمراجعة.</p></section>',
                    'changes' => ['تحديث المثال العملي'],
                    'sources_used' => ['https://example.com/platform-update'],
                ];
            }
        });

        $this->actingAs($admin)->post(route('admin.content.learning-update', $lesson), [
            'sources' => 'https://example.com/platform-update',
            'notes' => 'حدّث أمثلة القنوات فقط.',
        ])->assertRedirect(route('admin.content.edit', $lesson));

        $this->assertDatabaseHas('learning_content_update_drafts', [
            'content_id' => $lesson->id,
            'requested_by' => $admin->id,
            'status' => 'draft',
        ]);
        $this->assertSame($original, $lesson->fresh()->body_html);
        $this->actingAs($admin)->get(route('admin.content.edit', $lesson))
            ->assertOk()
            ->assertSee('مسودة تحديث ذكية')
            ->assertSee('تحديث أمثلة اختيار القناة');
    }
}
