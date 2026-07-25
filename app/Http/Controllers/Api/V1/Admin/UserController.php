<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, AdminUserController $users): JsonResponse
    {
        $payload = $users->payload($request);

        return response()->json([
            'data' => $payload['users']->values()->all(),
            'meta' => [
                'query' => $payload['search'],
                'limit' => 50,
            ],
        ]);
    }
}
