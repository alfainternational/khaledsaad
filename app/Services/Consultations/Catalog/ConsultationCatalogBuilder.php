<?php

namespace App\Services\Consultations\Catalog;

use App\Models\BlueprintModule;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\DiagnosticModule;
use App\Models\ModuleQuestion;
use App\Models\QuestionDefinition;
use App\Models\QuestionVersion;
use App\Models\Tool;
use App\Models\ToolField;
use Illuminate\Support\Facades\DB;

class ConsultationCatalogBuilder
{
    public function __construct(private readonly ConsultationCatalogValidator $validator) {}

    public function publishDefault(): ConsultationBlueprint
    {
        return DB::transaction(function (): ConsultationBlueprint {
            $config = config('consultation');
            $blueprint = ConsultationBlueprint::updateOrCreate(
                ['key' => $config['blueprint']['key']],
                ['name' => $config['blueprint']['name'], 'status' => 'published'],
            );
            if ($blueprint->currentVersion?->status === 'published'
                && $blueprint->currentVersion->version >= $config['blueprint']['version']) {
                $this->validator->validate($blueprint->currentVersion);

                return $blueprint->refresh();
            }
            $version = ConsultationBlueprintVersion::updateOrCreate(
                ['consultation_blueprint_id' => $blueprint->id, 'version' => $config['blueprint']['version']],
                ['status' => 'published', 'settings' => ['depth_limits' => $config['depth_limits']], 'published_at' => now(), 'locked_at' => now()],
            );

            $bindings = [];
            foreach ($config['modules'] as $index => $item) {
                $tool = $item['tool'] ? Tool::where('key', $item['tool'])->first() : null;
                $module = DiagnosticModule::updateOrCreate(
                    ['key' => $item['key']],
                    ['name' => $item['name'], 'tool_id' => $tool?->id, 'sort_order' => $index],
                );
                $bindings[$item['key']] = BlueprintModule::updateOrCreate(
                    ['blueprint_version_id' => $version->id, 'diagnostic_module_id' => $module->id],
                    ['importance' => ($item['required'] ?? false) ? 'core' : 'supporting', 'required' => $item['required'] ?? false, 'sort_order' => $index],
                );
            }

            foreach ($config['gateway_questions'] as $index => $item) {
                $question = $this->question($item['key'], $item['variable'], null, $item['text'], $item['type'] ?? 'select', $item['options'], true);
                $this->bind($bindings['scope'], $question, $index, true, 5);
            }

            $toolToModules = collect($config['modules'])->filter(fn ($item) => $item['tool'])->groupBy('tool')->map(fn ($items) => $items->pluck('key')->all())->all();
            ToolField::with('toolVersion.tool')->whereHas('toolVersion.tool', fn ($query) => $query->where('status', 'published'))->get()
                ->each(function (ToolField $field) use ($bindings, $toolToModules): void {
                    $toolKey = $field->toolVersion->tool->key;
                    $question = $this->question(
                        strtoupper($toolKey).'.'.strtoupper($field->key),
                        $field->key,
                        $field,
                        $field->label,
                        $field->type,
                        $field->options ?? [],
                        $field->required,
                        $field->help,
                        $field->why,
                    );
                    foreach ($toolToModules[$toolKey] ?? ['scope'] as $moduleKey) {
                        $this->bind($bindings[$moduleKey], $question, $field->sort_order, $field->required, $field->required ? 4 : 2, $field->visible_when);
                    }
                });

            $blueprint->forceFill(['current_version_id' => $version->id])->save();
            $this->validator->validate($version);

            return $blueprint->refresh();
        });
    }

    private function question(string $key, string $variable, ?ToolField $legacy, string $text, string $type, array $options, bool $required, ?string $help = null, ?string $why = null): QuestionVersion
    {
        $definition = QuestionDefinition::updateOrCreate(
            ['key' => $key],
            ['internal_variable' => $variable, 'sensitivity' => in_array($variable, ['monthly_budget', 'revenue', 'profit'], true) ? 'sensitive' : 'normal', 'inferable' => $legacy !== null, 'legacy_tool_field_id' => $legacy?->id],
        );

        $normalizedOptions = array_map(fn ($option) => is_array($option) ? $option : ['value' => $option, 'label' => $option], $options);

        return QuestionVersion::updateOrCreate(
            ['question_definition_id' => $definition->id, 'version' => (int) config('consultation.blueprint.version', 1)],
            ['user_text' => $text, 'help_text' => $help, 'why_text' => $why, 'answer_type' => $type, 'options' => $normalizedOptions, 'required' => $required, 'allow_unknown' => true, 'allow_skip' => ! $required, 'locked_at' => now()],
        );
    }

    private function bind(BlueprintModule $module, QuestionVersion $question, int $sort, bool $critical, int $impact, ?array $showWhen = null): void
    {
        ModuleQuestion::updateOrCreate(
            ['blueprint_module_id' => $module->id, 'question_version_id' => $question->id],
            ['diagnostic_impact' => $impact, 'discrimination' => 3, 'answer_burden' => 2, 'critical' => $critical, 'show_when' => $showWhen, 'sort_order' => $sort],
        );
    }
}
