<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpdateAgencyBrandingRequest;
use App\Support\Agency\WhiteLabelResolver;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * White-label branding settings for agency workspaces (Phase د).
 * Route is gated by the `white_label` entitlement (agency / enterprise plans).
 */
class AgencyBrandingController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function edit(Request $request, WhiteLabelResolver $whiteLabel): View
    {
        $workspace = $this->currentWorkspace($request);

        return view('app.agency.branding', [
            'workspace' => $workspace,
            'branding' => is_array($workspace->branding_json) ? $workspace->branding_json : [],
            'brand' => $whiteLabel->for($workspace),
        ]);
    }

    public function update(
        UpdateAgencyBrandingRequest $request,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);

        $workspace->update([
            'branding_json' => [
                'enabled' => $request->boolean('enabled'),
                'name' => $request->input('name') ?: $workspace->name,
                'color' => $request->input('color') ?: WhiteLabelResolver::DEFAULT_COLOR,
                'logo_url' => $request->input('logo_url'),
            ],
        ]);

        return redirect()
            ->route('agency.branding.edit')
            ->with('status', $flash->updated('علامة الوكالة'));
    }
}
