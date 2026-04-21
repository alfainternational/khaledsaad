<?php

namespace Tests\Feature\Admin;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Tool\Models\Tool;
use App\Models\User;
use Database\Seeders\PlatformBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolAndTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_crud_tools_and_ai_templates(): void
    {
        $this->seed(PlatformBootstrapSeeder::class);
        $admin = User::query()->where('is_super_admin', true)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.tools.store'), [
            'code' => 'audit-tool',
            'name' => 'Audit Tool',
            'description' => 'Runs a structured audit.',
            'module' => 'modules.stage_2',
            'stage' => 2,
            'sort_order' => 10,
            'status' => 'published',
        ])->assertRedirect();

        $tool = Tool::query()->where('code', 'audit-tool')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.tools.update', $tool), [
            'code' => 'audit-tool',
            'name' => 'Audit Tool Updated',
            'description' => 'Updated desc.',
            'module' => 'modules.stage_3',
            'stage' => 3,
            'sort_order' => 11,
            'status' => 'beta',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('tools', [
            'id' => $tool->id,
            'name' => 'Audit Tool Updated',
            'stage' => 3,
        ]);

        $this->actingAs($admin)->post(route('admin.ai-templates.store'), [
            'code' => 'sales-brief',
            'name' => 'Sales Brief',
            'description' => 'Compose a sales brief.',
            'prompt_template' => 'Write brief for {{project_name}} and {{client_name}}.',
            'model' => 'gpt-5',
            'credit_cost' => 1,
            'status' => 'published',
            'module' => 'modules.stage_4',
        ])->assertRedirect();

        $template = AITemplate::query()->where('code', 'sales-brief')->firstOrFail();

        $this->actingAs($admin)->delete(route('admin.tools.destroy', $tool))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.ai-templates.destroy', $template))->assertRedirect();

        $this->assertDatabaseMissing('tools', ['id' => $tool->id]);
        $this->assertDatabaseMissing('ai_templates', ['id' => $template->id]);
    }
}
