<?php

namespace App\Application\Admin\Plans;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Plan;
use App\Domain\Entitlement\Models\Entitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletePlanAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Plan $plan, User $actor): void
    {
        DB::transaction(function () use ($plan, $actor): void {
            Entitlement::query()
                ->where('scope_type', 'plan')
                ->where('scope_id', $plan->getKey())
                ->delete();

            $planId = $plan->getKey();
            $code = $plan->code;

            $plan->delete();

            $this->auditLogger->record(
                action: 'admin.plan.deleted',
                targetType: 'plan',
                targetId: $planId,
                actor: $actor,
                meta: ['code' => $code]
            );
        });
    }
}
