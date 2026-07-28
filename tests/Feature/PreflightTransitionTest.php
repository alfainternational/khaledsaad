<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\User;
use Database\Seeders\PaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * فحص ما قبل التحوّل.
 *
 * كل بند فيه كان سيتحوّل إلى عطل صامت بعد المسح، حين لا يبقى ما يُرجع إليه.
 */
class PreflightTransitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_healthy_server_passes(): void
    {
        $this->seed(PaymentSeeder::class);
        User::factory()->create(['is_admin' => true]);

        $this->artisan('platform:preflight')
            ->expectsOutputToContain('الخادم جاهز للتحوّل')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_gateway_encrypted_with_another_key_blocks_the_transition(): void
    {
        $this->seed(PaymentSeeder::class);
        User::factory()->create(['is_admin' => true]);

        /*
         * نحاكي مفتاحًا مبدَّلًا بكتابة قيمة لم تُشفَّر بمفتاح هذه البيئة.
         * الصف يبدو سليمًا تمامًا، ولا يظهر خللُه إلا عند محاولة الفكّ —
         * وبعد المسح لا يبقى ما يُرجع إليه.
         */
        DB::table('payment_gateways')->limit(1)->update([
            'credentials' => 'eyJpdiI6Im5vdC1hLXJlYWwtcGF5bG9hZCIsInZhbHVlIjoieHh4In0=',
        ]);

        $this->artisan('platform:preflight')
            ->expectsOutputToContain('التحوّل محجوب')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_missing_admin_blocks_the_transition(): void
    {
        $this->seed(PaymentSeeder::class);
        User::factory()->create(['is_admin' => false]);

        $this->artisan('platform:preflight')->assertExitCode(1);
    }

    #[Test]
    public function it_only_reads_and_never_writes(): void
    {
        $this->seed(PaymentSeeder::class);
        User::factory()->create(['is_admin' => true]);

        $before = [
            'gateways' => DB::table('payment_gateways')->count(),
            'users' => DB::table('users')->count(),
            'migrations' => DB::table('migrations')->count(),
        ];

        $this->artisan('platform:preflight')->assertExitCode(0);

        // فحص يكتب شيئًا يفقد صلاحيته كفحص: لا يمكن تكراره بلا أثر.
        $this->assertSame($before['gateways'], DB::table('payment_gateways')->count());
        $this->assertSame($before['users'], DB::table('users')->count());
        $this->assertSame($before['migrations'], DB::table('migrations')->count());
    }

    #[Test]
    public function it_never_prints_the_key_or_any_credential(): void
    {
        $this->seed(PaymentSeeder::class);
        User::factory()->create(['is_admin' => true]);

        $gateway = PaymentGateway::query()->firstOrFail();
        $gateway->update(['credentials' => ['secret_token' => 'SUPER-SECRET-VALUE']]);

        $this->artisan('platform:preflight')
            ->doesntExpectOutputToContain('SUPER-SECRET-VALUE')
            ->doesntExpectOutputToContain((string) config('app.key'))
            ->assertExitCode(0);
    }
}
