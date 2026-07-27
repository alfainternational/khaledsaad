<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * إجابة محفوظة بنوع مخالف لنوع الحقل الحالي (مصفوفة لحقل select مثلًا)
 * يجب ألا تسقط صفحة الخطوة بخطأ 500 — كانت تنفجر في field.blade.php.
 */
class ToolFieldValueCoercionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function a_saved_array_answer_does_not_crash_a_single_value_field(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع التحويل']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);

        // نلوّث كل الإجابات بمصفوفات كما لو جاءت من ذاكرة أداة أخرى بنوع مختلف.
        $selectKeys = $run->toolVersion->fields
            ->whereIn('type', ['select', 'text', 'textarea', 'number'])
            ->pluck('key');

        foreach ($selectKeys as $key) {
            $run->answers()->create(['field_key' => $key, 'value_json' => ['قيمة أولى', 'قيمة ثانية']]);
        }

        foreach ($run->toolVersion->fields->pluck('step')->unique() as $step) {
            $this->actingAs($user)
                ->get(route('app.runs.step', [$run->uuid, $step]))
                ->assertOk();
        }
    }
}
