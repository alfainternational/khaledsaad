<?php

namespace App\Application\Admin\Plans;

use App\Application\Admin\Support\NormalizesEntitlementValue;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Models\Plan;
use App\Domain\Entitlement\Models\Entitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertPlanAction
{
    use NormalizesEntitlementValue;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?Plan $plan = null): Plan
    {
        return DB::transaction(function () use ($data, $actor, $plan): Plan {
            $isNew = $plan === null;

            $plan ??= new Plan;
            $plan->fill([
                'code' => $data['code'],
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'monthly_price' => $data['monthly_price'],
                'annual_price' => $data['annual_price'] ?? null,
                'paypal_plan_id_monthly' => filled($data['paypal_plan_id_monthly'] ?? null) ? $data['paypal_plan_id_monthly'] : null,
                'paypal_plan_id_annual' => filled($data['paypal_plan_id_annual'] ?? null) ? $data['paypal_plan_id_annual'] : null,
                'status' => $data['status'],
            ]);
            $plan->save();

            Entitlement::query()
                ->where('scope_type', 'plan')
                ->where('scope_id', $plan->getKey())
                ->delete();

            $snapshot = [];

            foreach ($data['entitlements'] ?? [] as $row) {
                if (($row['key'] ?? null) === null || $row['key'] === '') {
                    continue;
                }

                $normalizedValue = $this->normalizeValue($row['value_type'], $row['value'] ?? null);

                Entitlement::query()->create([
                    'scope_type' => 'plan',
                    'scope_id' => $plan->getKey(),
                    'key' => $row['key'],
                    'value_type' => $row['value_type'],
                    'value' => $normalizedValue,
                    'source' => 'plan_default',
                ]);

                $snapshot[$row['key']] = $normalizedValue['value'];
            }

            $plan->forceFill(['features_json' => $snapshot])->save();

            $this->auditLogger->record(
                action: $isNew ? 'admin.plan.created' : 'admin.plan.updated',
                targetType: 'plan',
                targetId: $plan->getKey(),
                actor: $actor,
                meta: ['code' => $plan->code]
            );

            return $plan->refresh();
        });
    }
}
