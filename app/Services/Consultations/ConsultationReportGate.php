<?php

namespace App\Services\Consultations;

use Illuminate\Validation\ValidationException;

class ConsultationReportGate
{
    /** @param array<string,mixed> $snapshot */
    public function validate(array $snapshot): void
    {
        $errors = [];
        foreach (($snapshot['priorities'] ?? []) as $index => $item) {
            foreach (['title', 'description', 'root_cause', 'commercial_impact', 'action_steps', 'owner_role', 'resources', 'timeframe', 'kpi_definition', 'kpi_source', 'success_condition', 'stop_condition', 'risks', 'confidence', 'source_report_id'] as $field) {
                if (! array_key_exists($field, $item) || $item[$field] === null || $item[$field] === '' || $item[$field] === []) {
                    $errors["priorities.{$index}.{$field}"] = 'حقل مطلوب في عقد التوصية التنفيذية.';
                }
            }
            if (blank($item['baseline'] ?? null) && blank($item['missing_baseline_reason'] ?? null)) {
                $errors["priorities.{$index}.baseline"] = 'يلزم خط أساس أو سبب معلن لغيابه.';
            }
            if (($item['confidence'] ?? -1) < 0 || ($item['confidence'] ?? 101) > 100) {
                $errors["priorities.{$index}.confidence"] = 'الثقة يجب أن تكون بين 0 و100.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
