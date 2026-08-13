<?php

namespace App\Modules\Reporting\Objectives;

use Illuminate\Support\Collection;

class ObjectiveCatalog
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $definitions = null;

    /** @return array<int, string> */
    public function allowedForTool(string $toolKey): array
    {
        return $this->all()
            ->filter(fn (array $item) => in_array($toolKey, $item['tools'] ?? [], true))
            ->pluck('slug')
            ->values()
            ->all();
    }

    public function defaultForTool(string $toolKey): ?string
    {
        return $this->allowedForTool($toolKey)[0] ?? null;
    }

    public function forField(string $toolKey, string $fieldKey): ?string
    {
        $overrides = [
            'known_cac' => 'establish-measurement-baseline',
            'measurement' => 'establish-measurement-baseline',
            'tracking' => 'establish-measurement-baseline',
            'competitor_names' => 'competitor-analysis',
            'target_audience' => 'define-audience',
            'audience' => 'define-audience',
            'value_proposition' => 'clarify-positioning',
        ];

        return $overrides[$fieldKey] ?? $this->defaultForTool($toolKey);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        return $this->definitions ??= collect(require database_path('data/reporting/objectives.php'));
    }
}
