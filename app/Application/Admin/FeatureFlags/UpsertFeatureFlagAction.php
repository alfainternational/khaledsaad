<?php

namespace App\Application\Admin\FeatureFlags;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\FeatureFlag\Models\FeatureFlagAudience;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpsertFeatureFlagAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?FeatureFlag $featureFlag = null): FeatureFlag
    {
        return DB::transaction(function () use ($data, $actor, $featureFlag): FeatureFlag {
            $isNew = $featureFlag === null;
            $featureFlag ??= new FeatureFlag;

            $featureFlag->fill([
                'key' => $data['key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'module' => $data['module'] ?? null,
                'status' => $data['status'],
                'rollout_percentage' => $data['rollout_percentage'],
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            $featureFlag->save();

            FeatureFlagAudience::query()
                ->where('feature_flag_id', $featureFlag->getKey())
                ->delete();

            foreach ($data['audiences'] ?? [] as $row) {
                FeatureFlagAudience::query()->create([
                    'feature_flag_id' => $featureFlag->getKey(),
                    'audience_type' => $row['audience_type'],
                    'audience_id' => $row['audience_id'],
                ]);
            }

            $this->auditLogger->record(
                action: $isNew ? 'admin.feature-flag.created' : 'admin.feature-flag.updated',
                targetType: 'feature_flag',
                targetId: $featureFlag->getKey(),
                actor: $actor,
                meta: ['key' => $featureFlag->key]
            );

            return $featureFlag->load('audiences');
        });
    }
}
