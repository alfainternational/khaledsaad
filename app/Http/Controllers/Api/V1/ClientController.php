<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Client\Models\Client;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpsertClientRequest;
use App\Http\Resources\V1\ClientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * عملاء الوكالة (Agency clients) — CRUD كامل بعزل مساحة العمل.
 */
class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageClients', $workspace);

        $rows = Client::query()
            ->where('workspace_id', $workspace->id)
            ->withCount('projects')
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        return ClientResource::collection($rows);
    }

    public function store(UpsertClientRequest $request): JsonResponse
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');
        $this->authorize('manageClients', $workspace);

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
        ] + $this->attributesFrom($request));

        return (new ClientResource($client))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpsertClientRequest $request): ClientResource
    {
        $client = $this->resolve((string) $request->route('clientPublicId'));
        $this->authorize('update', $client);

        $client->update($this->attributesFrom($request));

        return new ClientResource($client);
    }

    public function destroy(Request $request): Response
    {
        $client = $this->resolve((string) $request->route('clientPublicId'));
        $this->authorize('delete', $client);

        $client->delete();

        return response()->noContent();
    }

    private function resolve(string $publicId): Client
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        return Client::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(UpsertClientRequest $request): array
    {
        $data = $request->validated();

        return [
            'name' => $data['name'],
            'contact_info' => [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'status' => $data['status'],
        ];
    }
}
