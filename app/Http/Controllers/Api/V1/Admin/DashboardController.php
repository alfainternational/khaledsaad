<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardController $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->payload()]);
    }
}
