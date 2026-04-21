<?php

namespace App\Support\Tooling;

use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;

class ToolModePolicy
{
    /**
     * @return array<int, string>
     */
    public function availableModes(Tool $tool): array
    {
        return array_values(array_filter([
            $tool->has_guided_mode ? 'guided' : null,
            $tool->has_structured_mode ? 'structured' : null,
            $tool->has_expert_mode ? 'expert' : null,
        ]));
    }

    /**
     * @return array<string, array{available: bool, reason: string|null}>
     */
    public function availability(
        Tool $tool,
        ?ToolRun $latestRun,
        int $runCount,
        ?string $awareness = null,
    ): array {
        $availableModes = $this->availableModes($tool);
        $completeness = (int) ($latestRun?->completeness_score ?? 0);
        $normalizedAwareness = (string) ($awareness ?? '');

        $canStructured = $runCount >= 1 || $completeness >= 35 || in_array($normalizedAwareness, ['structured', 'expert'], true);
        $canExpert = ($runCount >= 2 && $completeness >= 70)
            || (in_array($normalizedAwareness, ['expert'], true) && $runCount >= 1 && $completeness >= 60);

        $availability = [];

        foreach ($availableModes as $mode) {
            $availability[$mode] = match ($mode) {
                'guided' => ['available' => true, 'reason' => null],
                'structured' => [
                    'available' => $canStructured,
                    'reason' => $canStructured
                        ? null
                        : 'أكمل أول تشغيل بالمدخل البسيط، أو ارفع درجة اكتمال المشروع لفتح المستوى المرتّب.',
                ],
                'expert' => [
                    'available' => $canExpert,
                    'reason' => $canExpert
                        ? null
                        : 'يتطلب هذا المستوى نتائج أكثر نضجًا: تشغيلات متكررة ودرجة اكتمال أعلى.',
                ],
                default => ['available' => true, 'reason' => null],
            };
        }

        return $availability;
    }

    public function fallbackMode(Tool $tool): string
    {
        return $this->availableModes($tool)[0] ?? 'guided';
    }

    public function resolveMode(
        Tool $tool,
        string $requestedMode,
        ?ToolRun $latestRun,
        int $runCount,
        ?string $awareness = null,
    ): ?string {
        $availability = $this->availability($tool, $latestRun, $runCount, $awareness);

        if (($availability[$requestedMode]['available'] ?? false) === true) {
            return $requestedMode;
        }

        return null;
    }
}
