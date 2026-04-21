<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Users\DeleteUserAction;
use App\Application\Admin\Users\ImpersonateUserAction;
use App\Application\Admin\Users\ResetUserPasswordAction;
use App\Application\Admin\Users\SetUserStatusAction;
use App\Application\Admin\Users\UpsertUserAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImpersonateUserRequest;
use App\Http\Requests\Admin\UpsertAdminUserRequest;
use App\Http\Requests\Admin\UserResetPasswordRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Models\User;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'managedUser' => new User([
                'locale' => 'ar',
                'status' => UserStatus::Active,
                'is_super_admin' => false,
            ]),
            'method' => 'POST',
            'action' => route('admin.users.store'),
        ]);
    }

    public function store(
        UpsertAdminUserRequest $request,
        UpsertUserAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $user = $action->handle($request->validated(), $request->user());

        return redirect()->route('admin.users.show', $user)->with('status', $flash->created('المستخدم'));
    }

    public function show(User $user): View
    {
        $user->load([
            'ownedAccounts.subscription.plan',
            'accountMemberships.account.subscription.plan',
            'workspaceMemberships.workspace.account',
        ]);

        return view('admin.users.show', ['managedUser' => $user]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'managedUser' => $user,
            'method' => 'PUT',
            'action' => route('admin.users.update', $user),
        ]);
    }

    public function update(
        UpsertAdminUserRequest $request,
        User $user,
        UpsertUserAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $user);

        return redirect()->route('admin.users.show', $user)->with('status', $flash->updated('بيانات المستخدم'));
    }

    public function updateStatus(
        UserStatusRequest $request,
        User $user,
        SetUserStatusAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $action->handle(
            target: $user,
            status: UserStatus::from($request->validated('status')),
            actor: $request->user(),
        );

        return back()->with('status', $flash->statusUpdated('المستخدم'));
    }

    public function resetPassword(
        UserResetPasswordRequest $request,
        User $user,
        ResetUserPasswordAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse
    {
        $temporaryPassword = $action->handle($user, $request->user());

        return back()
            ->with('status', $flash->passwordReset())
            ->with('temporary_password', $temporaryPassword);
    }

    public function destroy(User $user, DeleteUserAction $action, FlashMessageCatalog $flash): RedirectResponse
    {
        $action->handle($user, request()->user());

        return redirect()->route('admin.users.index')->with('status', $flash->deleted('المستخدم'));
    }

    public function impersonate(
        ImpersonateUserRequest $request,
        User $user,
        ImpersonateUserAction $action,
    ): RedirectResponse {
        $action->handle($user, $request->user(), $request);

        return redirect()
            ->route('dashboard')
            ->with('status', 'أنت الآن تتصفح المنصة باسم المستخدم المختار. استخدم «إنهاء الانتحال» للعودة.');
    }
}
