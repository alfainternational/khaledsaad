<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tool\Models\Tool;
use App\Http\Resources\V1\ToolResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ToolIndexController
{
    /**
     * فهرس الأدوات المتاحة (المنشورة/بيتا) مرتّبة حسب المرحلة.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tools = Tool::query()
            ->whereIn('status', ['published', 'beta'])
            ->orderBy('stage')
            ->orderBy('sort_order')
            ->get();

        return ToolResource::collection($tools);
    }
}
