<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdvancedFileCanaryCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_enqueues_v2_jobs_in_an_isolated_account_and_cleans_everything(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $directory = sys_get_temp_dir().'/ks-canary-'.bin2hex(random_bytes(5));
        mkdir($directory);
        foreach (['image.png', 'text.pdf', 'scan.pdf', 'table.docx', 'formula.xlsx'] as $name) {
            file_put_contents($directory.'/'.$name, 'canary-'.$name);
        }

        try {
            $this->artisan('knowledge:file-canary', [
                'action' => 'enqueue',
                '--directory' => $directory,
                '--owner-user-id' => $user->id,
                '--json' => true,
            ])->assertSuccessful();

            $this->assertDatabaseHas('accounts', ['name' => '__AI_ADVANCED_FILE_CANARY__']);
            $this->assertDatabaseCount('knowledge_uploads', 5);
            $this->assertDatabaseCount('intelligence_jobs', 5);
            foreach (IntelligenceJob::query()->get() as $job) {
                $this->assertSame('v2', $job->payload_json['extraction_contract']['version']);
            }

            $this->artisan('knowledge:file-canary', ['action' => 'cleanup', '--json' => true])
                ->assertSuccessful();

            $this->assertDatabaseMissing('accounts', ['name' => '__AI_ADVANCED_FILE_CANARY__']);
            $this->assertDatabaseCount('knowledge_uploads', 0);
            $this->assertDatabaseCount('intelligence_jobs', 0);
            Storage::disk('local')->assertMissing('knowledge-uploads/canary');
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($directory);
        }
    }
}
