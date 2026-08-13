<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\ContentFeedback;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * التغذية الراجعة على التقرير: إشارة التعلّم الأرخص والأصدق في المنصة.
 */
class FeedbackController extends Controller
{
    use ResolvesWorkspace;

    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $validated = $request->validate([
            'verdict' => 'required|in:up,down',
            'note' => 'nullable|string|max:500',
        ]);

        ContentFeedback::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'subject_type' => Report::class,
                'subject_id' => $report->id,
            ],
            [
                'verdict' => $validated['verdict'],
                'note' => $validated['note'] ?? null,
            ],
        );

        return back()->with('status', $validated['verdict'] === ContentFeedback::VERDICT_UP
            ? __('شكرًا — تقييمك يعلّم المنصة ما ينفعك.')
            : __('سُجّلت ملاحظتك — التقييمات السلبية تُراجع لتحسين التقارير القادمة.'));
    }
}
