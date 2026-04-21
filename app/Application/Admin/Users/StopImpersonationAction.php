<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StopImpersonationAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request): User
    {
        $adminId = $request->session()->pull('impersonator_user_id');
        if (! is_numeric($adminId)) {
            abort(403, 'لا يوجد جلسة انتحال نشطة.');
        }

        /** @var User|null $current */
        $current = $request->user();
        $targetEmail = $current?->email;

        $admin = User::query()->findOrFail((int) $adminId);
        Auth::login($admin);
        $request->session()->regenerate();

        $this->auditLogger->record(
            action: 'admin.user.impersonation.stopped',
            targetType: 'user',
            targetId: $admin->getKey(),
            actor: $admin,
            workspace: null,
            meta: [
                'impersonated_email' => $targetEmail,
            ],
        );

        return $admin;
    }
}
