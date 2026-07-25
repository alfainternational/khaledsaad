<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\UsageController as AdminUsageController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __invoke(Request $request, AdminUsageController $usage): JsonResponse
    {
        return response()->json(['data' => $usage->payload($request)]);
    }
}
