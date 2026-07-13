<?php

namespace Tests\Unit;

use App\Support\Intelligence\RemoteUrlGuard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoteUrlGuardTest extends TestCase
{
    #[Test]
    public function it_rejects_unresolved_hosts_instead_of_connecting_with_unpinned_dns(): void
    {
        $result = (new RemoteUrlGuard)->inspect('https://definitely-unresolved.invalid/page');

        $this->assertFalse($result['allowed']);
        $this->assertSame('blocked_unresolved_host', $result['reason']);
        $this->assertSame([], $result['resolved_ips']);
    }
}
