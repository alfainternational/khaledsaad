<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Worker\WorkerJobLeaser;
use App\Domain\AI\Worker\WorkerJobLifecycle;
use App\Domain\AI\Worker\WorkerProtocolException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateWorkerController extends Controller
{
    public function lease(Request $request, WorkerJobLeaser $leaser): JsonResponse|Response
    {
        $data = $request->validate([
            'capabilities' => ['required', 'array', 'min:1', 'max:20'],
            'capabilities.*' => ['required', 'string', 'regex:/\A[a-z0-9_.-]{2,48}\z/D'],
            'version' => ['nullable', 'string', 'max:80'],
            'runtime' => ['nullable', 'array'],
            'runtime.python' => ['nullable', 'string', 'max:40'],
            'runtime.tools' => ['nullable', 'array', 'max:20'],
            'runtime.tools.*' => ['nullable', 'string', 'max:120'],
            'runtime.ocr_languages' => ['nullable', 'array', 'max:20'],
            'runtime.ocr_languages.*' => ['string', 'regex:/\A[a-z0-9_+-]{2,20}\z/D'],
        ]);

        try {
            $lease = $leaser->lease(
                app('currentPrivateWorker'),
                app('currentPrivateWorkerSecret'),
                $data['capabilities'],
                is_array($data['runtime'] ?? null) ? $data['runtime'] : [],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'WORKER_CAPABILITY_INVALID',
            ], 422);
        }

        return $lease === null
            ? response()->noContent()
            : response()->json(['data' => $lease]);
    }

    public function heartbeat(Request $request, WorkerJobLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'lease_token' => ['required', 'string', 'min:16', 'max:200'],
            'progress' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $job = $lifecycle->heartbeat(
                app('currentPrivateWorker'),
                (string) $request->route('jobPublicId'),
                $data['lease_token'],
                (int) $data['progress'],
            );

            return response()->json(['data' => [
                'status' => $job->status,
                'progress' => $job->progress,
                'leased_until' => $job->leased_until?->toIso8601String(),
            ]]);
        } catch (WorkerProtocolException $exception) {
            return $this->protocolError($exception);
        }
    }

    public function complete(Request $request, WorkerJobLifecycle $lifecycle): JsonResponse
    {
        if (strlen($request->getContent()) > (int) config('services.private_worker.max_result_bytes', 1048576)) {
            return response()->json(['message' => 'Worker result exceeds the allowed size.', 'code' => 'WORKER_RESULT_TOO_LARGE'], 413);
        }
        $data = $request->validate([
            'lease_token' => ['required', 'string', 'min:16', 'max:200'],
            'result' => ['required', 'array'],
            'model_name' => ['nullable', 'string', 'max:120'],
            'model_version' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $completed = $lifecycle->complete(
                app('currentPrivateWorker'),
                (string) $request->route('jobPublicId'),
                $data['lease_token'],
                $data['result'],
                $data['model_name'] ?? null,
                $data['model_version'] ?? null,
            );

            return response()->json(['data' => [
                'status' => $completed['job']->status,
                'output_hash' => $completed['job']->output_hash,
                'idempotent' => $completed['idempotent'],
            ]]);
        } catch (WorkerProtocolException $exception) {
            return $this->protocolError($exception);
        }
    }

    public function fail(Request $request, WorkerJobLifecycle $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'lease_token' => ['required', 'string', 'min:16', 'max:200'],
            'error_code' => ['required', 'string', 'regex:/\A[A-Z0-9_]{2,64}\z/D'],
            'message' => ['required', 'string', 'max:800'],
        ]);

        try {
            $job = $lifecycle->fail(
                app('currentPrivateWorker'),
                (string) $request->route('jobPublicId'),
                $data['lease_token'],
                $data['error_code'],
                $data['message'],
            );

            return response()->json(['data' => ['status' => $job->status]]);
        } catch (WorkerProtocolException $exception) {
            return $this->protocolError($exception);
        }
    }

    public function input(Request $request, WorkerJobLifecycle $lifecycle): StreamedResponse|JsonResponse
    {
        try {
            $upload = $lifecycle->inputUpload(
                app('currentPrivateWorker'),
                (string) $request->route('jobPublicId'),
                (string) $request->header('X-Worker-Lease-Token'),
            );

            return Storage::disk($upload->disk)->download($upload->path, $upload->original_name, [
                'Content-Type' => $upload->mime_type,
                'Cache-Control' => 'no-store, private',
            ]);
        } catch (WorkerProtocolException $exception) {
            return $this->protocolError($exception);
        }
    }

    private function protocolError(WorkerProtocolException $exception): JsonResponse
    {
        return response()->json([
            'message' => $exception->getMessage(),
            'code' => $exception->protocolCode,
        ], $exception->status);
    }
}
