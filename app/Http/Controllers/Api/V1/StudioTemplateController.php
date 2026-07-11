<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Models\AITemplate;
use App\Http\Resources\V1\AiTemplateResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudioTemplateController
{
    /**
     * كتالوج قوالب الاستوديو المتاحة.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = AITemplate::query()
            ->where('status', 'active')
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return AiTemplateResource::collection($templates);
    }
}
