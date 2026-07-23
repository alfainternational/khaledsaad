<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\PulseDigest;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحة النبض: أرشيف الخلاصات الأسبوعية لمشاريع مساحة عمل المستخدم.
 */
class PulseController extends Controller
{
    public function index(Request $request): View
    {
        $workspace = $request->user()->primaryWorkspace();

        $digests = PulseDigest::where('workspace_id', $workspace->id)
            ->with('project:id,name,slug')
            ->orderByDesc('week_start')
            ->orderBy('project_id')
            ->limit(60)
            ->get();

        // العرض بالأسابيع: أحدث أسبوع أولًا، وداخله المشاريع.
        $weeks = $digests
            ->groupBy(fn (PulseDigest $digest) => $digest->week_start->toDateString())
            ->map(fn ($group, $weekStart) => [
                'week_start' => $group->first()->week_start,
                'digests' => $group->values(),
            ])
            ->values();

        return view('app.pulse.index', ['weeks' => $weeks]);
    }
}
