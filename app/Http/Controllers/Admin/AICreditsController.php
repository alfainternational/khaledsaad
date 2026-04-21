<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Ai\GrantAccountAiCreditsAction;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AICreditsLedger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GrantAiCreditsRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AICreditsController extends Controller
{
    public function index(Request $request): View
    {
        $entries = AICreditsLedger::query()
            ->with('account.owner')
            ->when($request->string('account_id')->isNotEmpty(), fn ($query) => $query->where('account_id', $request->integer('account_id')))
            ->when($request->string('reason')->isNotEmpty(), fn ($query) => $query->where('reason', 'like', '%'.$request->string('reason')->value().'%'))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $lowBalances = DB::table('ai_credits_ledger')
            ->select('account_id', DB::raw('SUM(delta) as balance'))
            ->groupBy('account_id')
            ->havingRaw('SUM(delta) < ?', [10])
            ->orderBy('balance')
            ->limit(12)
            ->get();

        $accountsById = Account::query()
            ->with('owner')
            ->whereIn('id', $lowBalances->pluck('account_id'))
            ->get()
            ->keyBy('id');

        $lowBalanceRows = $lowBalances->map(function ($row) use ($accountsById): array {
            $account = $accountsById->get($row->account_id);

            return [
                'account' => $account,
                'balance' => (int) $row->balance,
            ];
        });

        return view('admin.ai-credits.index', [
            'entries' => $entries,
            'lowBalanceRows' => $lowBalanceRows,
            'accountsForGrant' => Account::query()->with('owner')->orderBy('name')->limit(400)->get(),
        ]);
    }

    public function store(
        GrantAiCreditsRequest $request,
        GrantAccountAiCreditsAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $account = Account::query()->findOrFail($request->integer('account_id'));
        $action->handle($account, (int) $request->validated('delta'), (string) $request->validated('reason'), $request->user());

        return back()->with('status', $flash->updated('رصيد AI للحساب'));
    }
}
