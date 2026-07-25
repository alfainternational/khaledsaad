<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Services\Guests\GuestSessionManager;
use App\Services\Tools\ToolRunService;
use App\Support\Presentation\RunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GuestRunController extends Controller
{
    public function __construct(
        private readonly GuestSessionManager $guests,
        private readonly ToolRunService $service,
        private readonly RunPresenter $presenter,
    ) {}

    public function start(Request $request, Tool $tool): JsonResponse
    {
        abort_unless($tool->isRunnable(), 404);

        $data = $request->validate([
            'project_name' => 'nullable|string|max:120',
        ]);

        $guest = $this->guests->startForApi(
            $request->header('X-Guest-Token'),
            $data['project_name'] ?? 'مشروعي',
        );
        $session = $guest['session'];

        try {
            $run = $this->service->start(
                $this->guests->project($session, $data['project_name'] ?? 'مشروعي'),
                $tool,
                null,
                $session->id,
            );
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'data' => [
                'guest_token' => $guest['created'] ? $guest['token'] : null,
                'session_created' => $guest['created'],
                'run' => $this->presenter->wizard($run),
            ],
        ], 201);
    }

    public function show(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json(['data' => $this->presenter->wizard($run)]);
    }

    public function saveStep(Request $request, ToolRun $run, int $step): JsonResponse
    {
        $this->authorizeRun($request, $run);

        $this->service->saveStep($run, $step, $request->all());

        return response()->json(['data' => $this->presenter->wizard($run->refresh())]);
    }

    public function preflight(Request $request, ToolRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        return response()->json([
            'data' => [
                'run' => $this->presenter->wizard($run),
                'preflight' => $this->service->preflight($run),
            ],
        ]);
    }

    private function authorizeRun(Request $request, ToolRun $run): GuestSession
    {
        $session = $this->guests->currentForApi($request->header('X-Guest-Token'));

        if (! $session instanceof GuestSession || $run->guest_session_id !== $session->id) {
            throw new NotFoundHttpException;
        }

        return $session;
    }
}
