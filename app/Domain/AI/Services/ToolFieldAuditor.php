<?php

namespace App\Domain\AI\Services;

use App\Domain\Tool\Models\Tool;
use App\Support\Tooling\ToolBlueprintCatalog;

/**
 * مدقّق حقول الأدوات: يكشف مشاكل الاستمارات آلياً ليعالجها فريق التطوير.
 *
 * يفحص: حقول الاختيار التي ربما يجب أن تكون كتابة حرّة، الحقول النصّية بلا
 * تعليمات، والحقول التي تعليماتها تعدّد خيارات (مرشّحة لتصبح اختياراً).
 */
class ToolFieldAuditor
{
    public function __construct(private readonly ToolBlueprintCatalog $blueprints) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $selectFields = [];
        $missingInstructions = [];
        $enumeratedTextFields = [];
        $textCount = 0;
        $toolCount = 0;

        foreach (Tool::query()->orderBy('stage')->get(['code', 'stage']) as $tool) {
            $toolCount++;
            $bp = $this->blueprints->for($tool->code);

            foreach (($bp['modes'] ?? []) as $mode => $modeDef) {
                foreach (($modeDef['fields'] ?? []) as $field) {
                    $type = $field['type'] ?? 'text';
                    $label = (string) ($field['label'] ?? ($field['key'] ?? '?'));
                    $tip = trim((string) ($field['answer_tip'] ?? ''));
                    $ref = $tool->code.' · '.$mode.' · '.$label;

                    if ($type === 'select') {
                        $selectFields[] = ['ref' => $ref, 'options' => count($field['options'] ?? [])];

                        continue;
                    }

                    $textCount++;

                    if ($tip === '') {
                        $missingInstructions[] = $ref;
                    } elseif (preg_match('/مثال\s*:.*[،,].*[،,]/u', $tip)) {
                        // التعليمة تعدّد 3+ خيارات → ربما الأنسب اختيار، أو العكس (راجِع).
                        $enumeratedTextFields[] = ['ref' => $ref, 'tip' => $tip];
                    }
                }
            }
        }

        return [
            'tool_count' => $toolCount,
            'text_count' => $textCount,
            'select_count' => count($selectFields),
            'select_fields' => $selectFields,
            'missing_instructions' => $missingInstructions,
            'enumerated_text_fields' => $enumeratedTextFields,
            'issues_total' => count($missingInstructions) + count($enumeratedTextFields),
        ];
    }
}
