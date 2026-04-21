<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonateUserAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $target, User $admin, Request $request): void
    {
        if ($target->is_super_admin && $target->id !== $admin->id) {
            throw new AuthorizationException('لا يمكن انتحال صفة مدير عام آخر.');
        }

        $status = $target->status instanceof UserStatus
            ? $target->status
            : UserStatus::from((string) $target->status);

        if ($status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'user' => 'لا يمكن انتحال صفة مستخدم غير نشط.',
            ]);
        }

        $request->session()->put('impersonator_user_id', $admin->id);

        Auth::login($target);
        $request->session()->regenerate();

        $this->auditLogger->record(
            action: 'admin.user.impersonation.started',
            targetType: 'user',
            targetId: $target->getKey(),
            actor: $admin,
            workspace: null,
            meta: [
                'target_email' => $target->email,
            ],
        );
    }
}
