<?php

namespace App\Http\Controllers\Web;

use App\Domain\Client\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Web\UpsertClientRequest;
use App\Support\PlatformSectionCatalog;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function index(Request $request, OnboardingState $state): View|RedirectResponse
    {
        if (! $request->user()) {
            return view('pages.section', PlatformSectionCatalog::section('agency'));
        }

        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageClients', $workspace);

        if (! $state->isCompleted($workspace)) {
            return redirect()->route('onboarding.show');
        }

        return view('app.clients.index', [
            'workspace' => $workspace,
            'clients' => Client::query()
                ->where('workspace_id', $workspace->id)
                ->withCount('projects')
                ->with(['projects' => fn ($query) => $query->latest()->limit(3)])
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageClients', $workspace);

        return view('app.clients.form', [
            'workspace' => $workspace,
            'client' => new Client,
            'action' => route('clients.store'),
            'method' => 'POST',
        ]);
    }

    public function store(UpsertClientRequest $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('manageClients', $workspace);
        $data = $request->validated();

        Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'contact_info' => [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'status' => $data['status'],
        ]);

        return redirect()->route('clients.index')->with('status', $flash->created('العميل'));
    }

    public function edit(Request $request, Client $client): View
    {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('update', $client);

        return view('app.clients.form', [
            'workspace' => $workspace,
            'client' => $client,
            'action' => route('clients.update', $client),
            'method' => 'PUT',
        ]);
    }

    public function update(UpsertClientRequest $request, Client $client, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('update', $client);
        $data = $request->validated();

        $client->update([
            'name' => $data['name'],
            'contact_info' => [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'status' => $data['status'],
        ]);

        return redirect()->route('clients.index')->with('status', $flash->updated('بيانات العميل'));
    }

    public function destroy(Request $request, Client $client, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')->with('status', $flash->deleted('العميل'));
    }
}
