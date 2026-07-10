<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Workspace\AcceptWorkspaceInvitationAction;
use App\Application\Workspace\InviteWorkspaceMemberAction;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Http\Api\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\InviteWorkspaceMemberRequest;
use App\Http\Resources\V1\InvitationResource;
use App\Http\Resources\V1\TeamMemberResource;
use App\Http\Resources\V1\WorkspaceSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeamController extends Controller
{
    /**
     * الأعضاء والدعوات في مساحة العمل الحالية.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageMembers', $workspace);

        return response()->json([
            'data' => [
                'members' => TeamMemberResource::collection(
                    $workspace->members()->with('user')->get()
                )->resolve($request),
                'invitations' => InvitationResource::collection(
                    $workspace->invitations()->latest()->get()
                )->resolve($request),
            ],
        ]);
    }

    public function invite(
        InviteWorkspaceMemberRequest $request,
        InviteWorkspaceMemberAction $action,
    ): JsonResponse {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageMembers', $workspace);

        $action->handle($workspace, $request->validated(), $request->user());

        return response()->json([
            'data' => ['message' => 'أُرسلت الدعوة بنجاح.'],
        ], 201);
    }

    /**
     * قبول دعوة بالرمز (token) — يعيد المساحة الجديدة ليتحول إليها التطبيق.
     */
    public function accept(
        Request $request,
        string $token,
        AcceptWorkspaceInvitationAction $action,
    ): JsonResponse {
        $invitation = WorkspaceInvitation::query()->where('token', $token)->firstOrFail();
        $action->handle($invitation, $request->user());

        return response()->json([
            'data' => [
                'message' => 'انضممت إلى مساحة العمل.',
                'workspace' => (new WorkspaceSummaryResource(
                    $invitation->workspace()->first()
                ))->resolve($request),
            ],
        ]);
    }

    public function destroyMember(Request $request): Response
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $member = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->route('memberId'));

        $this->authorize('delete', $member);

        if ($member->role === 'owner'
            && $workspace->members()->where('role', 'owner')->count() <= 1) {
            throw new ApiException('لا يمكن حذف آخر مالك للمساحة.', 'LAST_OWNER', 422);
        }

        $member->delete();

        return response()->noContent();
    }

    public function destroyInvitation(Request $request): Response
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $invitation = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $request->route('invitationId'));

        $this->authorize('delete', $invitation);

        $invitation->delete();

        return response()->noContent();
    }
}
