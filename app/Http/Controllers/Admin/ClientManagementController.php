<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Client\Models\Client;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $clients = Client::query()
            ->with('workspace.account')
            ->withCount('projects')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.clients.index', ['clients' => $clients]);
    }

    public function show(Client $client): View
    {
        $client->load([
            'workspace.account.owner',
            'projects' => fn ($query) => $query->withCount('toolRuns')->latest(),
        ]);

        return view('admin.clients.show', [
            'client' => $client,
            'clientStatuses' => ['active', 'archived'],
        ]);
    }

    public function edit(Client $client): View
    {
        $client->load('workspace');

        return view('admin.clients.form', [
            'client' => $client,
            'method' => 'PUT',
            'action' => route('admin.clients.update', $client),
        ]);
    }

    public function update(Request $request, Client $client, FlashMessageCatalog $flash): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_info' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,archived'],
        ]);

        $client->update($validated);

        $this->auditLogger->record(
            action: 'admin.client.updated',
            targetType: 'client',
            targetId: $client->id,
            actor: $request->user(),
            meta: ['name' => $client->name],
        );

        return back()->with('status', $flash->updated('العميل'));
    }

    public function destroy(Client $client, FlashMessageCatalog $flash): RedirectResponse
    {
        $name = $client->name;
        $id = $client->id;
        $client->delete();

        $this->auditLogger->record(
            action: 'admin.client.deleted',
            targetType: 'client',
            targetId: $id,
            actor: request()->user(),
            meta: ['name' => $name],
        );

        return redirect()->route('admin.clients.index')->with('status', $flash->deleted('العميل'));
    }
}
