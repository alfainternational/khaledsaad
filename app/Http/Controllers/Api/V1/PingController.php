<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class PingController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'api' => 'v1',
            ],
        ]);
    }
}
