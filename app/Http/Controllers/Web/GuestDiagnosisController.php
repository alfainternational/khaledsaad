<?php

namespace App\Http\Controllers\Web;

use App\Domain\Intelligence\Models\DiagnosisCase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CaptureDiagnosisEmailRequest;
use App\Http\Requests\Web\StartDiagnosisRequest;
use App\Jobs\RunGuestDiagnosisJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestDiagnosisController extends Controller
{
    public function form(): View
    {
        return view('diagnose.start');
    }

    public function start(StartDiagnosisRequest $request): RedirectResponse
    {
        $competitor = trim((string) $request->input('competitor', ''));

        $case = DiagnosisCase::query()->create([
            'public_id' => (string) Str::ulid(),
            'input_url' => $this->normalizeUrl($request->input('input_url')),
            'business_name' => $request->input('business_name'),
            'case_type' => $request->input('case_type', 'website'),
            'goal' => $request->input('goal'),
            'competitors_json' => $competitor !== '' ? [['label' => $competitor, 'domain' => $competitor]] : [],
            'sector' => 'general',
            'status' => 'queued',
            'expires_at' => now()->addDays(7),
            'ip' => $request->ip(),
        ]);

        // Run inline (dispatchSync): production has no queue worker, so a queued job would
        // never complete. The analyzers are time-bounded by RemotePageFetcher timeouts.
        RunGuestDiagnosisJob::dispatchSync($case->id);

        // Remember the case so we can convert it right after the user registers.
        $request->session()->put('diagnosis_public_id', $case->public_id);

        return redirect()->route('diagnose.show', $case);
    }

    public function status(DiagnosisCase $case): JsonResponse
    {
        if ($case->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json([
            'status' => $case->status,
            'ready' => $case->isReady(),
            'executive_score' => $case->executive_score,
            'has_email' => $case->hasEmail(),
        ]);
    }

    public function captureEmail(CaptureDiagnosisEmailRequest $request, DiagnosisCase $case): RedirectResponse
    {
        abort_if($case->isExpired(), 410, 'انتهت صلاحية هذا التشخيص.');

        if (! $case->hasEmail()) {
            $case->update([
                'email' => $request->validated('email'),
                'email_captured_at' => now(),
            ]);
        }

        return redirect()->route('diagnose.show', $case);
    }

    public function show(Request $request, DiagnosisCase $case): View
    {
        if ($case->isExpired()) {
            return view('diagnose.expired', ['case' => $case]);
        }

        $partial = is_array($case->report_json) ? ($case->report_json['partial'] ?? null) : null;

        return view('diagnose.show', [
            'case' => $case,
            'partial' => $partial,
            'showResult' => $case->isReady() && $case->hasEmail(),
            'needsEmail' => $case->isReady() && ! $case->hasEmail(),
        ]);
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return null;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $url;
    }
}
