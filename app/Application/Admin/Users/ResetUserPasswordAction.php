<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Str;

class ResetUserPasswordAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $target, User $actor): string
    {
        $temporaryPassword = Str::password(14);

        $target->forceFill([
            'password' => $temporaryPassword,
        ])->save();

        $this->auditLogger->record(
            action: 'admin.user.password-reset',
            targetType: 'user',
            targetId: $target->getKey(),
            actor: $actor,
            meta: ['email' => $target->email]
        );

        return $temporaryPassword;
    }
}
