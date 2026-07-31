<?php

namespace Tests\Feature;

use App\Models\PromptVersion;
use App\Models\Tool;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * أمر tool:release: تعديل برومبت مقفل لا يتم بصمت — يُسكّ إصدار جديد
 * ببرومبتات غير مقفلة والقديم يبقى أثرًا (BR-012).
 */
class ToolReleaseCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $definitionBackup = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
        $this->definitionBackup = (string) file_get_contents($this->definitionPath());
    }

    protected function tearDown(): void
    {
        // الأمر يرفع رقم الإصدار في ملف التعريف — يُعاد كما كان حتى لا
        // يتسرب أثر الاختبار إلى شجرة العمل.
        file_put_contents($this->definitionPath(), $this->definitionBackup);
        parent::tearDown();
    }

    #[Test]
    public function releasing_mints_a_new_unlocked_version_and_keeps_the_locked_one_as_history(): void
    {
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $current = $tool->currentVersion ?? $tool->versions()->orderByDesc('version')->firstOrFail();

        // قفل برومبت التركيب وتمييز tier كما يحدث في الإنتاج بعد أول استخدام.
        $prompt = PromptVersion::where('tool_version_id', $current->id)->firstOrFail();
        $prompt->forceFill(['locked_at' => now(), 'tier' => 'advanced'])->save();

        $this->artisan('tool:release', ['key' => 'marketing-score'])->assertSuccessful();

        $tool->refresh();
        $released = $tool->versions()->orderByDesc('version')->first();

        // إصدار جديد أعلى، وهو الفعّال.
        $this->assertSame($current->version + 1, $released->version);
        $this->assertSame($released->id, $tool->current_version_id);

        // برومبتات الجديد غير مقفلة، وtier محمول من القديم.
        $fresh = PromptVersion::where('tool_version_id', $released->id)
            ->where('stage', $prompt->stage)
            ->firstOrFail();
        $this->assertNull($fresh->locked_at);
        $this->assertSame('advanced', $fresh->tier);

        // القديم المقفل باقٍ أثرًا لم يُمس.
        $this->assertNotNull($prompt->fresh()->locked_at);

        // ملف التعريف لحق بالإصدار الجديد — يبقى مصدر الحقيقة.
        $definition = require $this->definitionPath();
        $this->assertSame($released->version, $definition['version']['number']);
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $before = $tool->versions()->count();

        $this->artisan('tool:release', ['key' => 'marketing-score', '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $tool->versions()->count());
        $this->assertSame($this->definitionBackup, (string) file_get_contents($this->definitionPath()));
    }

    #[Test]
    public function reseeding_after_a_release_never_downgrades_the_active_version(): void
    {
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();

        $this->artisan('tool:release', ['key' => 'marketing-score'])->assertSuccessful();
        $releasedId = $tool->fresh()->current_version_id;

        // ملف التعريف يُعاد لرقمه القديم (محاكاة نسيان الدفع بعد إصدار على بيئة أخرى).
        file_put_contents($this->definitionPath(), $this->definitionBackup);

        $this->seed(ToolCatalogSeeder::class);

        // المؤشر لا يرتد للإصدار الأقدم بصمت.
        $this->assertSame($releasedId, $tool->fresh()->current_version_id);
    }

    #[Test]
    public function an_unknown_tool_fails_clearly(): void
    {
        $this->artisan('tool:release', ['key' => 'no-such-tool'])->assertFailed();
    }

    private function definitionPath(): string
    {
        return database_path('data/tools/marketing-score.php');
    }
}
