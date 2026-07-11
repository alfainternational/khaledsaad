<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Worker\WorkerJobLeaser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class PrivateWorkerController extends Controller
{
    public function lease(Request $request, WorkerJobLeaser $leaser): JsonResponse|Response
    {
        $data = $request->validate([
            'capabilities' => ['required', 'array', 'min:1', 'max:20'],
            'capabilities.*' => ['required', 'string', 'regex:/\A[a-z0-9_.-]{2,48}\z/D'],
            'version' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $lease = $leaser->lease(
                app('currentPrivateWorker'),
                app('currentPrivateWorkerSecret'),
                $data['capabilities'],
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
}
