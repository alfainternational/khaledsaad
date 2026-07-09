<?php

namespace App\Http\Controllers\Admin;

use App\Domain\AI\Kernel\Agents\AgentCatalog;
use App\Domain\AI\Kernel\Agents\AgentDefinition;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * عارض كتالوج قدرات الوكلاء — سطح الآدمن لـ«الكشف الانتقائي» (الدستور §32).
 *
 * يعرض القدرات الـ25 (تجسيدات وكلاء digital-marketing-pro) مجمّعةً حسب العنقود،
 * مع حالتها في دورة الحياة وموجة تنفيذها وصلاحيتها ومفتاح ميزتها — للقراءة فقط
 * في هذه المرحلة (المصدر config/agent_registry.php).
 */
class AgentCatalogController extends Controller
{
    /** @var array<string, string> */
    private const CLUSTERS = [
        'intelligence' => 'الذكاء والاستشعار',
        'planning' => 'التخطيط والقياس',
        'creation' => 'المحتوى والقنوات',
        'gate' => 'بوابة الجودة',
        'execution' => 'التنفيذ والعمليات',
        'memory' => 'التعلّم والذاكرة',
    ];

    public function __construct(private readonly AgentCatalog $catalog) {}

    public function index(): View
    {
        $all = $this->catalog->all();

        $grouped = [];
        foreach (self::CLUSTERS as $key => $label) {
            $items = array_values($this->catalog->forCluster($key));
            if ($items !== []) {
                $grouped[$label] = $items;
            }
        }

        return view('admin.agents.index', [
            'total' => count($all),
            'grouped' => $grouped,
            'byWave' => $this->countBy($all, fn (AgentDefinition $d): int => $d->wave),
            'byStatus' => $this->countBy($all, fn (AgentDefinition $d): string => $d->status),
        ]);
    }

    /**
     * @param  array<string, AgentDefinition>  $items
     * @return array<int|string, int>
     */
    private function countBy(array $items, callable $key): array
    {
        $out = [];
        foreach ($items as $definition) {
            $k = $key($definition);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        ksort($out);

        return $out;
    }
}
