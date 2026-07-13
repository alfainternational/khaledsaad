<?php

namespace Tests\Unit;

use App\Domain\Project\Models\Project;
use App\Http\Controllers\Api\ToolRunApiController;
use App\Http\Controllers\Web\ToolController;
use ReflectionClass;
use Tests\TestCase;

class ToolBriefingDecorationTest extends TestCase
{
    public function test_ready_tool_action_links_to_the_current_form(): void
    {
        $decorated = $this->decorateWithWebController([
            'next_action' => [
                'action_type' => 'current_tool',
                'reason' => 'جاهزة للتشغيل.',
            ],
        ]);

        $this->assertSame('#tool-form', $decorated['next_action']['cta_url']);
        $this->assertSame('ابدأ تشغيل الأداة الآن', $decorated['next_action']['cta_label']);
    }

    public function test_brief_like_tool_action_links_to_project_brief_edit(): void
    {
        $decorated = $this->decorateWithApiController([
            'next_action' => [
                'action_type' => 'tool',
                'recommended_tool_code' => null,
                'recommended_tool_label' => 'تعديل ملف مشروعك',
                'reason' => 'أكمل ملف المشروع أولاً.',
            ],
        ]);

        $this->assertSame(route('projects.brief.edit', $this->project()), $decorated['next_action']['cta_url']);
        $this->assertSame('تعديل ملف المشروع', $decorated['next_action']['cta_label']);
    }

    /**
     * @param  array<string, mixed>  $toolBriefing
     * @return array<string, mixed>
     */
    private function decorateWithWebController(array $toolBriefing): array
    {
        return $this->invokePrivate(new ToolController, 'decorateToolBriefing', [$toolBriefing, $this->project()]);
    }

    /**
     * @param  array<string, mixed>  $toolBriefing
     * @return array<string, mixed>
     */
    private function decorateWithApiController(array $toolBriefing): array
    {
        return $this->invokePrivate(new ToolRunApiController, 'decorateToolBriefing', [$toolBriefing, $this->project()]);
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function invokePrivate(object $object, string $method, array $arguments): array
    {
        $reflection = new ReflectionClass($object);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($object, $arguments);
    }

    private function project(): Project
    {
        $project = new Project;
        $project->setRawAttributes(['id' => 123], true);
        $project->exists = true;

        return $project;
    }
}
