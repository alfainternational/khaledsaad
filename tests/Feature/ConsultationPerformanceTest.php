<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Consultations\ConsultationPresenter;
use App\Services\Consultations\ConsultationService;
use App\Services\Projects\ProjectService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function start_and_present_have_bounded_query_counts(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'أداء', 'stage' => 'growth']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $session = app(ConsultationService::class)->start($project, $user);
        $startQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        app(ConsultationPresenter::class)->show($session->refresh());
        $presentQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(80, $startQueries, 'بدء الاستشارة تجاوز ميزانية الاستعلامات.');
        $this->assertLessThanOrEqual(25, $presentQueries, 'عرض الحالة تجاوز ميزانية الاستعلامات.');
    }
}
