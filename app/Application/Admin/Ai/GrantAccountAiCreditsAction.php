<?php

namespace App\Application\Admin\Ai;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AICreditsLedger;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GrantAccountAiCreditsAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Account $account, int $delta, string $reason, User $actor): AICreditsLedger
    {
        $delta = (int) $delta;
        if ($delta === 0) {
            throw ValidationException::withMessages([
                'delta' => 'قيمة الرصيد يجب أن تكون غير صفرية.',
            ]);
        }

        $entry = AICreditsLedger::query()->create([
            'account_id' => $account->getKey(),
            'delta' => $delta,
            'reason' => $reason,
            'ref_id' => null,
        ]);

        $this->auditLogger->record(
            action: 'admin.ai_credits.granted',
            targetType: 'account',
            targetId: $account->getKey(),
            actor: $actor,
            workspace: null,
            meta: [
                'delta' => $delta,
                'reason' => $reason,
                'ledger_id' => $entry->getKey(),
            ],
        );

        return $entry;
    }
}
