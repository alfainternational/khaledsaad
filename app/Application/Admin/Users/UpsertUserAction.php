<?php

namespace App\Application\Admin\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertUserAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{name:string,email:string,password?:string|null,locale:string,status:string,is_super_admin:bool}  $data
     */
    public function handle(array $data, User $actor, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $actor, $user): User {
            $isNew = $user === null;
            $user ??= new User;

            $status = UserStatus::from($data['status']);
            $isSuperAdmin = (bool) ($data['is_super_admin'] ?? false);

            $this->guardLastActiveSuperAdmin($user, $status, $isSuperAdmin);

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $data['locale'],
                'status' => $status,
                'is_super_admin' => $isSuperAdmin,
            ]);

            if (($data['password'] ?? null) !== null && $data['password'] !== '') {
                $user->password = $data['password'];
            }

            $user->save();

            $this->auditLogger->record(
                action: $isNew ? 'admin.user.created' : 'admin.user.updated',
                targetType: 'user',
                targetId: $user->getKey(),
                actor: $actor,
                meta: [
                    'email' => $user->email,
                    'status' => $user->status->value,
                    'is_super_admin' => $user->is_super_admin,
                ],
            );

            return $user->refresh();
        });
    }

    private function guardLastActiveSuperAdmin(User $user, UserStatus $status, bool $isSuperAdmin): void
    {
        if (! $user->exists || ! $user->is_super_admin) {
            return;
        }

        if ($isSuperAdmin && $status === UserStatus::Active) {
            return;
        }

        $remainingActiveSuperAdmins = User::query()
            ->where('is_super_admin', true)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($user->getKey())
            ->count();

        abort_if($remainingActiveSuperAdmins === 0, 422, 'لا يمكن تعطيل آخر مدير عام نشط.');
    }
}
