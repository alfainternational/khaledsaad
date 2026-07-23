<?php

namespace App\Services\Tools;

use App\Models\Tool;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Support\Collection;

/**
 * مصدر واحد لعرض الأدوات في الواجهة العامة.
 *
 * الصفحة الرئيسية وصفحة الأدوات العامة تقرآن من قاعدة البيانات نفسها التي
 * تشغّل لوحة العمل — فلا يعد الموقع بأداة غير موجودة، ولا يخفي أداة جاهزة.
 */
class ToolShowcase
{
    public function __construct(private readonly ToolPresenter $presenter) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cards(?int $limit = null): array
    {
        return $this->query()
            ->when($limit !== null, fn (Collection $tools) => $tools->take($limit))
            ->map(fn (Tool $tool) => $this->presenter->card($tool))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Tool $tool): array
    {
        return $this->presenter->detail($tool->load(['currentVersion.fields']));
    }

    /**
     * الأداة التي يبدأ منها الزائر افتراضيًا: أول أداة قابلة للتشغيل بالترتيب.
     */
    public function entryTool(): ?array
    {
        $tool = Tool::runnable()->with('currentVersion')->orderBy('sort_order')->first();

        return $tool !== null ? $this->presenter->card($tool) : null;
    }

    /**
     * @return array{total: int, runnable: int, coming_soon: int}
     */
    public function stats(): array
    {
        $total = Tool::count();
        $runnable = Tool::runnable()->count();

        return [
            'total' => $total,
            'runnable' => $runnable,
            'coming_soon' => $total - $runnable,
        ];
    }

    /**
     * @return Collection<int, Tool>
     */
    private function query(): Collection
    {
        return Tool::with('currentVersion')
            ->orderByRaw('CASE WHEN status = ? AND current_version_id IS NOT NULL THEN 0 ELSE 1 END', [Tool::STATUS_PUBLISHED])
            ->orderBy('sort_order')
            ->get();
    }
}
