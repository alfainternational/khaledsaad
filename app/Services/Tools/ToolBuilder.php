<?php

namespace App\Services\Tools;

use App\Models\PromptVersion;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolVersion;
use Illuminate\Support\Facades\DB;

/**
 * يبني/يحدّث أداة كاملة من تعريف واحد.
 *
 * مصدر واحد لمنطق التركيب يستخدمه البذر ولوحة الآدمن معًا، فما يُبنى من
 * الملفات وما يُبنى من الواجهة يتبعان القواعد نفسها (BR-012 لقفل البرومبت).
 */
class ToolBuilder
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function sync(array $definition): Tool
    {
        return DB::transaction(function () use ($definition): Tool {
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
                    'sort_order' => $definition['sort_order'] ?? 0,
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

            $this->syncFields($version, $definition['fields'] ?? []);
            $this->syncPrompts($version, $definition['prompts'] ?? []);

            $tool->forceFill(['current_version_id' => $version->id])->save();

            return $tool->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function syncFields(ToolVersion $version, array $fields): void
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
                    'sort_order' => $field['sort_order'] ?? $index,
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
    public function syncPrompts(ToolVersion $version, array $prompts): void
    {
        foreach ($prompts as $stage => $content) {
            $existing = PromptVersion::firstWhere([
                'tool_version_id' => $version->id,
                'stage' => $stage,
            ]);

            // BR-012: برومبت مقفل لا يُلمس، حتى من لوحة الآدمن.
            if ($existing?->locked_at !== null) {
                continue;
            }

            PromptVersion::updateOrCreate(
                ['tool_version_id' => $version->id, 'stage' => $stage],
                ['content' => trim($content), 'status' => 'published', 'tier' => $existing?->tier ?? 'standard'],
            );
        }
    }
}
