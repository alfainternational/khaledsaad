<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Enums\UserStatus;
use App\Models\User;

class SetUserStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $target, UserStatus $status, User $actor): User
    {
        if ($target->is_super_admin && $status === UserStatus::Frozen && User::query()->where('is_super_admin', true)->where('status', UserStatus::Active)->count() <= 1) {
            abort(422, 'لا يمكن تجميد آخر مدير عام نشط.');
        }

        $target->forceFill(['status' => $status])->save();

        $this->auditLogger->record(
            action: $status === UserStatus::Frozen ? 'admin.user.frozen' : 'admin.user.unfrozen',
            targetType: 'user',
            targetId: $target->getKey(),
            actor: $actor,
            meta: ['email' => $target->email]
        );

        return $target->refresh();
    }
}
