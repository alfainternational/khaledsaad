<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ToolRun;
use App\Modules\Shared\I18n\TranslatedConfig;
use App\Services\Tools\ToolShowcase;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly ToolShowcase $showcase) {}

    public function __invoke(): View
    {
        return view('home', [
            'brand' => TranslatedConfig::get('brand'),
            'tools' => $this->showcase->cards(limit: 8),
            'toolStats' => $this->showcase->stats(),
            'entryTool' => $this->showcase->entryTool(),
            'proof' => $this->proof(),
            'knowledge' => Content::query()
                ->published()
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
        ]);
    }

    /**
     * برهان حقيقي لا شعار (بند ٤): عدد التشخيصات المكتملة فعلًا خلال ٣٠
     * يومًا ومتوسط درجاتها. يظهر فقط حين تبلغ العينة ١٠ فأكثر (§٤.٢)،
     * ومعه أساسه دائمًا (§١٣). كاش قصير لأن الصفحة الأولى الأعلى زيارة.
     *
     * @return array{count: int, average: int}|null
     */
    private function proof(): ?array
    {
        return Cache::remember('home.proof', now()->addMinutes(10), function (): ?array {
            $stats = ToolRun::where('status', ToolRun::STATUS_COMPLETED)
                ->where('completed_at', '>=', now()->subDays(30))
                ->whereNotNull('base_score')
                ->selectRaw('count(*) as n, avg(base_score) as avg_score')
                ->first();

            if ($stats === null || (int) $stats->n < 10) {
                return null;
            }

            return ['count' => (int) $stats->n, 'average' => (int) round((float) $stats->avg_score)];
        });
    }
}
