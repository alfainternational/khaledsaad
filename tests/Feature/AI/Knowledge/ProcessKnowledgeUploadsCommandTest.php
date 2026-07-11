<?php

namespace Tests\Feature\AI\Knowledge;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessKnowledgeUploadsCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    public function test_empty_upload_queue_is_successful(): void
    {
        $this->artisan('knowledge:process-uploads')
            ->expectsOutput('Indexed: 0; failed: 0')
            ->assertSuccessful();
    }

    public function test_processing_limit_is_bounded_for_shared_hosting(): void
    {
        $this->artisan('knowledge:process-uploads', ['--limit' => 101])
            ->expectsOutput('The upload processing limit must be between 1 and 100.')
            ->assertExitCode(2);
    }
}
