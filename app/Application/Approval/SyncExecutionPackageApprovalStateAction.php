<?php

namespace App\Application\Approval;

use App\Domain\Approval\Models\Approval;
use App\Domain\Execution\Models\ExecutionPackage;

class SyncExecutionPackageApprovalStateAction
{
    public function markRequested(Approval $approval): void
    {
        $this->updatePackageStatus($approval, [
            'proposed' => 'in_review',
        ]);
    }

    public function applyDecision(Approval $approval): void
    {
        $this->updatePackageStatus($approval, match ($approval->status) {
            'approved' => [
                'proposed' => 'approved',
                'in_review' => 'approved',
            ],
            'rejected' => [
                'in_review' => 'proposed',
                'approved' => 'proposed',
            ],
            'pending' => [
                'proposed' => 'in_review',
            ],
            default => [],
        });
    }

    /**
     * @param  array<string, string>  $transitions
     */
    private function updatePackageStatus(Approval $approval, array $transitions): void
    {
        if ($approval->item_type !== 'execution_package') {
            return;
        }

        $package = ExecutionPackage::query()
            ->where('workspace_id', $approval->workspace_id)
            ->where('project_id', $approval->project_id)
            ->whereKey($approval->item_id)
            ->first();

        if (! $package) {
            return;
        }

        $nextStatus = $transitions[$package->status] ?? null;

        if ($nextStatus === null) {
            return;
        }

        $package->update(['status' => $nextStatus]);
    }
}
