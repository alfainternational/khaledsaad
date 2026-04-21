<?php

namespace App\Application\Admin\FeatureFlags;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteFeatureFlagAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(FeatureFlag $featureFlag, User $actor): void
    {
        DB::transaction(function () use ($featureFlag, $actor): void {
            $flagId = $featureFlag->getKey();
            $flagKey = $featureFlag->key;
            $featureFlag->delete();

            $this->auditLogger->record(
                action: 'admin.feature-flag.deleted',
                targetType: 'feature_flag',
                targetId: $flagId,
                actor: $actor,
                meta: ['key' => $flagKey]
            );
        });
    }
}
