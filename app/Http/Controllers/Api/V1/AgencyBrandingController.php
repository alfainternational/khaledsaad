<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateAgencyBrandingRequest;
use App\Support\Agency\WhiteLabelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إعدادات العلامة البيضاء للوكالة — المسار محروس بـ entitlement:white_label.
 */
class AgencyBrandingController extends Controller
{
    public function show(Request $request, WhiteLabelResolver $whiteLabel): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        return response()->json([
            'data' => [
                'branding' => is_array($workspace->branding_json) ? $workspace->branding_json : [],
                'brand' => $whiteLabel->for($workspace),
            ],
        ]);
    }

    public function update(
        UpdateAgencyBrandingRequest $request,
        WhiteLabelResolver $whiteLabel,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $workspace->update([
            'branding_json' => [
                'enabled' => $request->boolean('enabled'),
                'name' => $request->input('name') ?: $workspace->name,
                'color' => $request->input('color') ?: WhiteLabelResolver::DEFAULT_COLOR,
                'logo_url' => $request->input('logo_url'),
            ],
        ]);

        return response()->json([
            'data' => [
                'branding' => $workspace->branding_json,
                'brand' => $whiteLabel->for($workspace->fresh()),
            ],
        ]);
    }
}
