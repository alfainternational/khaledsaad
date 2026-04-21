<?php

namespace Tests\Unit\Domain\Integration;

use App\Domain\Integration\Exceptions\CloudIntegrationException;
use App\Domain\Integration\Services\HttpCloudClient;
use App\Domain\Integration\Services\NullCloudClient;
use Tests\TestCase;

class CloudClientsTest extends TestCase
{
    public function test_null_client_is_not_configured_and_throws_on_get(): void
    {
        $client = new NullCloudClient;
        $this->assertFalse($client->configured());
        $this->expectException(CloudIntegrationException::class);
        $client->get('/test');
    }

    public function test_http_client_not_configured_when_disabled(): void
    {
        config([
            'cloud.enabled' => false,
            'cloud.base_url' => null,
        ]);
        $client = new HttpCloudClient;
        $this->assertFalse($client->configured());
        $this->expectException(CloudIntegrationException::class);
        $client->get('/test');
    }

    public function test_workspace_outbound_headers_include_public_id(): void
    {
        $workspace = new \App\Domain\Workspace\Models\Workspace;
        $workspace->public_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        $headers = \App\Application\Integration\CloudWorkspaceOutboundHeaders::forWorkspace($workspace);

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $headers['X-Workspace-Public-Id'] ?? null);
    }
}
