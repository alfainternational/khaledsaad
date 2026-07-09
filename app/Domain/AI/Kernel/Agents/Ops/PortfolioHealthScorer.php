<?php

namespace App\Domain\AI\Kernel\Agents\Ops;

/**
 * مُقيّم صحّة المحفظة — نواة وكيل agency-operations محلياً.
 *
 * يجمّع مؤشّرات كل عميل (0-100) إلى درجة صحّة وتصنيف RAG (أخضر/أصفر/أحمر)،
 * ويعيد توزيعاً على مستوى المحفظة. نقي بلا مورد خارجي. (قدرة hidden —
 * flag modules.agency_mode.)
 */
class PortfolioHealthScorer
{
    private const GREEN = 75;

    private const AMBER = 50;

    /**
     * @param  array<int, array{name: string, signals: array<int, int|float>}>  $clients
     * @return array{overall: int|null, distribution: array{green: int, amber: int, red: int}, clients: array<int, array{name: string, health: int, rag: string}>}
     */
    public function score(array $clients): array
    {
        $scored = [];
        $distribution = ['green' => 0, 'amber' => 0, 'red' => 0];

        foreach ($clients as $client) {
            $signals = array_values(array_map('floatval', (array) ($client['signals'] ?? [])));
            $health = $signals === [] ? 0 : (int) round(array_sum($signals) / count($signals));
            $rag = $this->rag($health);
            $distribution[$rag]++;

            $scored[] = [
                'name' => (string) ($client['name'] ?? 'عميل'),
                'health' => $health,
                'rag' => $rag,
            ];
        }

        $overall = $scored === []
            ? null
            : (int) round(array_sum(array_column($scored, 'health')) / count($scored));

        return [
            'overall' => $overall,
            'distribution' => $distribution,
            'clients' => $scored,
        ];
    }

    private function rag(int $health): string
    {
        return match (true) {
            $health >= self::GREEN => 'green',
            $health >= self::AMBER => 'amber',
            default => 'red',
        };
    }
}
