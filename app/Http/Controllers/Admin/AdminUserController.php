<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\CreditManager;
use App\Services\Billing\SubscriptionAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly CreditManager $credits,
        private readonly SubscriptionAssignmentService $assignments,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', $this->payload($request));
    }

    /**
     * @return array{users: Collection<int, array<string, mixed>>, search: string}
     */
    public function payload(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->withCount('workspaces')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (User $user): array {
                $workspace = $user->primaryWorkspace();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                    'workspaces' => $user->workspaces_count,
                    'balance' => $user->workspaces()->with('wallet')->get()
                        ->sum(fn ($workspace) => $workspace->wallet?->balance ?? 0),
                    'joined' => $user->created_at->translatedFormat('j F Y'),
                    'workspace_id' => $workspace->id,
                    'plan_id' => $workspace->subscription?->plan_id,
                    'plan' => $workspace->subscription?->plan?->name ?? '—',
                ];
            });

        return ['users' => $users, 'search' => $search];
    }

    public function grantCredits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'credits' => 'required|integer|min:1|max:100000',
        ]);

        $this->credits->grant(
            $user->primaryWorkspace(),
            $data['credits'],
            __('منحة من الإدارة'),
        );

        return back()->with('status', "أُضيف {$data['credits']} رصيدًا إلى {$user->name}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'balance' => $user->primaryWorkspace()->wallet?->balance ?? 0,
            'workspaces' => $user->workspaces()->with(['wallet', 'subscription.plan', 'subscription.scheduledPlan'])->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function bulkPlans(): View
    {
        return view('admin.users.bulk-plan', [
            'users' => User::with('workspaces.subscription.plan')->latest('id')->limit(200)->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function previewPlans(Request $request): View
    {
        $data = $this->planData($request, false);
        $plan = Plan::findOrFail($data['plan_id']);

        return view('admin.users.plan-preview', [
            'preview' => $this->assignments->preview($data['workspace_ids'], $plan, $data['credit_policy'], $data['effective']),
            'payload' => $data,
            'plan' => $plan,
        ]);
    }

    public function assignPlans(Request $request): RedirectResponse
    {
        $data = $this->planData($request, true);
        $plan = Plan::findOrFail($data['plan_id']);
        $result = $this->assignments->assign(
            $data['workspace_ids'], $plan, $request->user(), $data['credit_policy'],
            $data['effective'], $data['credit_amount'] ?? null,
        );

        $message = "حُدّثت {$result['succeeded']} مساحة";
        if ($result['failed'] > 0) {
            $message .= "، وتعذر تحديث {$result['failed']} مساحة";
        }

        return redirect()->route('admin.users.index')->with('status', $message.'.');
    }

    public function assignPlan(Request $request, User $user): RedirectResponse
    {
        $data = $this->planData($request, true, false);
        abort_unless($user->workspaces()->whereKey($data['workspace_id'])->exists(), 404);
        $plan = Plan::findOrFail($data['plan_id']);
        $this->assignments->assign(
            [(int) $data['workspace_id']], $plan, $request->user(), $data['credit_policy'],
            $data['effective'], $data['credit_amount'] ?? null,
        );

        return redirect()->route('admin.users.edit', $user)->with('status', __('حُدّثت خطة المستخدم وسُجل القرار.'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', __('حُدّث المستخدم.'));
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        // منع الآدمن من نزع صلاحيته عن نفسه فيقفل نفسه خارج اللوحة.
        if ($user->id === $request->user()->id && $user->isAdmin()) {
            return back()->withErrors(['admin' => __('لا يمكنك نزع صلاحيتك عن نفسك.')]);
        }

        $user->forceFill(['is_admin' => ! $user->isAdmin()])->save();

        return back()->with('status', $user->isAdmin() ? __('مُنحت صلاحية الإدارة.') : __('نُزعت صلاحية الإدارة.'));
    }

    /** @return array<string, mixed> */
    private function planData(Request $request, bool $confirm, bool $many = true): array
    {
        $rules = [
            $many ? 'workspace_ids' : 'workspace_id' => $many
                ? ['required', 'array', 'min:1', 'max:500']
                : ['required', 'integer', 'exists:workspaces,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'credit_policy' => ['required', 'in:keep,plan_grant,add'],
            'credit_amount' => ['nullable', 'required_if:credit_policy,add', 'integer', 'min:1', 'max:1000000'],
            'effective' => ['required', 'in:now,period_end'],
        ];
        if ($many) {
            $rules['workspace_ids.*'] = ['integer', 'distinct', 'exists:workspaces,id'];
        }
        if ($confirm) {
            $rules['confirmation'] = ['required', 'accepted'];
        }

        return $request->validate($rules);
    }
}
