<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\CreditManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly CreditManager $credits) {}

    public function index(Request $request): View
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
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'workspaces' => $user->workspaces_count,
                'balance' => $user->workspaces()->with('wallet')->get()
                    ->sum(fn ($workspace) => $workspace->wallet?->balance ?? 0),
                'joined' => $user->created_at->translatedFormat('j F Y'),
            ]);

        return view('admin.users.index', ['users' => $users, 'search' => $search]);
    }

    public function grantCredits(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'credits' => 'required|integer|min:1|max:100000',
        ]);

        $this->credits->grant(
            $user->primaryWorkspace(),
            $data['credits'],
            'منحة من الإدارة',
        );

        return back()->with('status', "أُضيف {$data['credits']} رصيدًا إلى {$user->name}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'balance' => $user->primaryWorkspace()->wallet?->balance ?? 0,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'حُدّث المستخدم.');
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        // منع الآدمن من نزع صلاحيته عن نفسه فيقفل نفسه خارج اللوحة.
        if ($user->id === $request->user()->id && $user->isAdmin()) {
            return back()->withErrors(['admin' => 'لا يمكنك نزع صلاحيتك عن نفسك.']);
        }

        $user->forceFill(['is_admin' => ! $user->isAdmin()])->save();

        return back()->with('status', $user->isAdmin() ? 'مُنحت صلاحية الإدارة.' : 'نُزعت صلاحية الإدارة.');
    }
}
