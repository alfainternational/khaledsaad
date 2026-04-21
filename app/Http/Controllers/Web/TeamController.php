<?php

namespace App\Http\Controllers\Web;

use App\Application\Workspace\AcceptWorkspaceInvitationAction;
use App\Application\Workspace\InviteWorkspaceMemberAction;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\InviteWorkspaceMemberRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageMembers', $workspace);

        return view('app.team', [
            'workspace' => $workspace,
            'members' => $workspace->members()->with('user')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
        ]);
    }

    public function invite(
        InviteWorkspaceMemberRequest $request,
        InviteWorkspaceMemberAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageMembers', $workspace);
        $action->handle($workspace, $request->validated(), $request->user());

        return back()->with('status', $flash->invitationCreated());
    }

    public function accept(
        Request $request,
        string $token,
        AcceptWorkspaceInvitationAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $invitation = WorkspaceInvitation::query()->where('token', $token)->firstOrFail();
        $action->handle($invitation, $request->user());
        $request->session()->put('current_workspace_id', $invitation->workspace_id);

        return redirect()->route('team.index')->with('status', $flash->invitationAccepted());
    }

    public function destroyMember(Request $request, WorkspaceMember $member, FlashMessageCatalog $flash): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('delete', $member);
        abort_if($member->role === 'owner' && $workspace->members()->where('role', 'owner')->count() <= 1, 422, 'لا يمكن حذف آخر مالك للمساحة.');

        $member->delete();

        return back()->with('status', $flash->memberRemoved());
    }

    public function destroyInvitation(Request $request, WorkspaceInvitation $invitation, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        $invitation->delete();

        return back()->with('status', $flash->invitationDeleted());
    }
}
