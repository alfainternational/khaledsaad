<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تدوير `APP_KEY` بلا فقدان سرّ.
 *
 * الخطر الذي يحرسه هذا الملف: `payment_gateways.credentials` و`settings` من
 * نوع `secret` و`device_tokens.token` كلها مشفَّرة بمفتاح التطبيق، ولا توجد
 * نسخة ثانية منها. تدوير المفتاح بلا إعادة تشفير يجعلها غير قابلة للفك إلى
 * الأبد — أي أربع بوابات دفع ميتة ومفاتيح مزوّدين مفقودة.
 */
class ReencryptSecretsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reencrypts_every_secret_after_a_key_rotation(): void
    {
        $old = $this->key();
        $this->useKey($old);

        PaymentGateway::create([
            'provider' => 'paypal',
            'label' => 'PayPal',
            'mode' => 'live',
            'credentials' => ['client_id' => 'abc', 'secret' => 'xyz'],
        ]);

        Setting::put('ai.api_key', 'sk-secret-value', 'ai', 'secret');
        Setting::put('site.name', 'خالد سعد', 'general', 'string');

        // التدوير: مفتاح جديد يكتب، والقديم يبقى للفكّ وحده.
        $new = $this->key();
        $this->useKey($new, previous: [$old]);

        $this->artisan('platform:reencrypt')->assertSuccessful();

        /*
         * التحقّق الحاسم: المفتاح القديم يُنزَع تمامًا. لو بقيت قيمةٌ مشفّرة
         * به لكان الاختبار يمرّ بينما الإنتاج ميّت بعد حذف المفتاح.
         */
        $this->useKey($new);

        $gateway = PaymentGateway::first();
        $this->assertSame(['client_id' => 'abc', 'secret' => 'xyz'], $gateway->credentials);
        $this->assertSame('sk-secret-value', Setting::get('ai.api_key'));
    }

    #[Test]
    public function a_plain_setting_is_left_untouched(): void
    {
        $this->useKey($this->key());

        Setting::put('site.name', 'خالد سعد', 'general', 'string');

        $before = DB::table('settings')->where('key', 'site.name')->value('value');

        $this->artisan('platform:reencrypt')->assertSuccessful();

        // النصّ الصريح ليس سرًّا: تشفيره هنا يجعله غير مقروء لمن يقرأ الإعداد.
        $this->assertSame($before, DB::table('settings')->where('key', 'site.name')->value('value'));
    }

    #[Test]
    public function a_row_that_cannot_be_decrypted_aborts_before_any_write(): void
    {
        $old = $this->key();
        $this->useKey($old);

        PaymentGateway::create([
            'provider' => 'paypal',
            'label' => 'PayPal',
            'mode' => 'live',
            'credentials' => ['client_id' => 'abc'],
        ]);

        Setting::put('ai.api_key', 'sk-secret-value', 'ai', 'secret');

        $gatewayBefore = DB::table('payment_gateways')->value('credentials');
        $settingBefore = DB::table('settings')->where('key', 'ai.api_key')->value('value');

        // مفتاح جديد بلا القديم: الفكّ يتعذّر — وهو ما يحدث لو نُسي
        // APP_PREVIOUS_KEYS.
        $this->useKey($this->key());

        $this->artisan('platform:reencrypt')->assertFailed();

        /*
         * لا كتابة جزئية. المرحلتان هما الغرض: تدويرٌ نصفه بمفتاح ونصفه بآخر
         * يترك بوابة دفع معطّلة لا يُعرف أيّها.
         */
        $this->assertSame($gatewayBefore, DB::table('payment_gateways')->value('credentials'));
        $this->assertSame($settingBefore, DB::table('settings')->where('key', 'ai.api_key')->value('value'));
    }

    #[Test]
    public function a_dry_run_verifies_without_writing(): void
    {
        $old = $this->key();
        $this->useKey($old);

        Setting::put('ai.api_key', 'sk-secret-value', 'ai', 'secret');

        $before = DB::table('settings')->where('key', 'ai.api_key')->value('value');

        $this->useKey($this->key(), previous: [$old]);

        $this->artisan('platform:reencrypt', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, DB::table('settings')->where('key', 'ai.api_key')->value('value'));
    }

    private function key(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc'));
    }

    /**
     * @param  array<int, string>  $previous
     */
    private function useKey(string $key, array $previous = []): void
    {
        config([
            'app.key' => $key,
            'app.previous_keys' => $previous,
        ]);

        // الحاوية تحفظ المشفِّر بمفتاحه؛ بلا إعادة بنائه يبقى القديم عاملًا.
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstances();

        // الإعدادات تُقرأ عبر كاش دائم؛ بلا مسحه تُقرأ قيمة فُكّت بمفتاح سابق.
        Cache::forget('settings.all');
    }
}
