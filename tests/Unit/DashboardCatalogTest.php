<?php

namespace Tests\Unit;

use App\Support\Dashboard\ReadinessCatalog;
use App\Support\Dashboard\StageCatalog;
use PHPUnit\Framework\TestCase;

class DashboardCatalogTest extends TestCase
{
    public function test_agency_audit_is_part_of_measurement_and_scale_journey(): void
    {
        $this->assertContains('agency-audit', StageCatalog::all()[5]['core_tools']);
        $this->assertContains('agency-audit', ReadinessCatalog::all()['measurement_readiness']['tools']);
    }
}
