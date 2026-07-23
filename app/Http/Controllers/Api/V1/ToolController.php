<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Support\Presentation\ToolPresenter;
use Illuminate\Http\JsonResponse;

class ToolController extends Controller
{
    public function __construct(private readonly ToolPresenter $presenter) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Tool::with('currentVersion')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Tool $tool) => $this->presenter->card($tool))
                ->all(),
        ]);
    }

    public function show(Tool $tool): JsonResponse
    {
        return response()->json([
            'data' => $this->presenter->detail($tool->load('currentVersion.fields')),
        ]);
    }
}
