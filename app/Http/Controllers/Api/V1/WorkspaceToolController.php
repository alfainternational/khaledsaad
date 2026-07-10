<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tooling\RunToolAction;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Api\ToolRunApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ExecuteToolRequest;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolFormExperienceBuilder;
use App\Support\Tooling\ToolModePolicy;
use App\Support\Tooling\ToolStrategicAdvisor;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceToolController extends Controller
{
    public function load(
        Request $request,
        ToolBlueprintCatalog $toolBlueprintCatalog,
        ToolFormExperienceBuilder $toolFormExperienceBuilder,
        WorkspaceProfileStore $profileStore,
        ProjectMarketingBriefStore $briefStore,
        ToolStrategicAdvisor $toolStrategicAdvisor,
    ): JsonResponse {
        $tcode = (string) $request->route('tcode');
        $tool = Tool::query()->where('code', $tcode)->firstOrFail();
        abort_unless($tool->status !== 'hidden', 404);

        $response = app(ToolRunApiController::class)->load(
            $request,
            $tool,
            $toolBlueprintCatalog,
            $toolFormExperienceBuilder,
            $profileStore,
            $briefStore,
            $toolStrategicAdvisor,
        );

        // إثراء الرد بمخطط نموذج موحّد للموبايل: تعريفات الحقول (label/type/options)
        // مدموجة مع بيانات التجربة (priority/hints/suggested_value/quality) — يقرأها
        // المُصيِّر الديناميكي في Flutter مباشرة دون Blade مُخدَّم.
        $payload = $response->getData(true);
        if (($payload['success'] ?? false) === true) {
            $payload['form'] = $this->buildMobileForm(
                $toolBlueprintCatalog->for($tool),
                is_array($payload['experience'] ?? null) ? $payload['experience'] : [],
            );
            $response->setData($payload);
        }

        return $response;
    }

    /**
     * يدمج تعريفات الحقول من الـ blueprint مع بيانات التجربة، بترتيب ثابت.
     *
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $experience
     * @return array<string, mixed>
     */
    private function buildMobileForm(array $blueprint, array $experience): array
    {
        $experienceModes = is_array($experience['modes'] ?? null) ? $experience['modes'] : [];
        $modes = [];

        foreach (($blueprint['modes'] ?? []) as $modeKey => $mode) {
            $experienceFields = is_array($experienceModes[$modeKey]['fields'] ?? null)
                ? $experienceModes[$modeKey]['fields']
                : [];

            $fields = [];
            foreach (($mode['fields'] ?? []) as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $meta = is_array($experienceFields[$key] ?? null) ? $experienceFields[$key] : [];

                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($field['label'] ?? $key),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'answer_tip' => (string) ($field['answer_tip'] ?? ''),
                    'options' => $this->normalizeOptions($field['options'] ?? null),
                    'priority' => $meta['priority'] ?? 'normal',
                    'priority_label' => $meta['priority_label'] ?? null,
                    'context_hint' => $meta['context_hint'] ?? null,
                    'smart_placeholder' => $meta['smart_placeholder'] ?? null,
                    'suggested_value' => $meta['suggested_value'] ?? null,
                    'suggestion_label' => $meta['suggestion_label'] ?? null,
                    'quality' => [
                        'min_length' => (int) ($meta['quality']['min_length'] ?? 0),
                        'generic_terms' => array_values((array) ($meta['quality']['generic_terms'] ?? [])),
                    ],
                ];
            }

            $modes[] = [
                'key' => (string) $modeKey,
                'label' => (string) ($mode['label'] ?? $modeKey),
                'description' => (string) ($mode['description'] ?? ''),
                'fields' => $fields,
            ];
        }

        return [
            'modes' => $modes,
            'default_mode' => $modes[0]['key'] ?? null,
        ];
    }

    /**
     * يحوّل خيارات select من خريطة {value: label} إلى قائمة مرتّبة.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $value => $label) {
            $normalized[] = [
                'value' => (string) $value,
                'label' => (string) $label,
            ];
        }

        return $normalized;
    }

    public function run(
        ExecuteToolRequest $request,
        RunToolAction $action,
        ToolModePolicy $toolModePolicy,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        $tcode = (string) $request->route('tcode');
        $tool = Tool::query()->where('code', $tcode)->firstOrFail();
        abort_unless($tool->status !== 'hidden', 404);

        return app(ToolRunApiController::class)->store(
            $request,
            $tool,
            $action,
            $toolModePolicy,
            $profileStore,
        );
    }
}
