<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tooling\RunToolAction;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Api\ToolRunApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ExecuteToolRequest;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolFormExperienceBuilder;
use App\Support\Tooling\ToolModePolicy;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceToolController extends Controller
{
    public function load(
        Request $request,
        ToolBlueprintCatalog $toolBlueprintCatalog,
        ToolFormExperienceBuilder $toolFormExperienceBuilder,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        $tcode = (string) $request->route('tcode');
        $tool = Tool::query()->where('code', $tcode)->firstOrFail();
        abort_unless($tool->status !== 'hidden', 404);

        return app(ToolRunApiController::class)->load(
            $request,
            $tool,
            $toolBlueprintCatalog,
            $toolFormExperienceBuilder,
            $profileStore,
        );
    }

    public function run(
        ExecuteToolRequest $request,
        RunToolAction $action,
        ToolModePolicy $toolModePolicy,
        WorkspaceProfileStore $profileStore,
    ): JsonResponse {
        $tcode = (string) $request->route('tcode');
        $tool = Tool::query()->where('code', $tcode)->firstOrFail();
        abort_unless($tool->status !== 'hidden', 404);

        return app(ToolRunApiController::class)->store(
            $request,
            $tool,
            $action,
            $toolModePolicy,
            $profileStore,
        );
    }
}
