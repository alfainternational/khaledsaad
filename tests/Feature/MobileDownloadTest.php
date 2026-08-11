<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileDownloadTest extends TestCase
{
    #[Test]
    public function the_server_flutter_package_and_request_header_share_one_build_number(): void
    {
        $pubspec = file_get_contents(base_path('mobile/pubspec.yaml'));
        $environment = file_get_contents(base_path('mobile/lib/core/config/app_environment.dart'));

        $this->assertIsString($pubspec);
        $this->assertIsString($environment);
        preg_match('/^version:\s*([^+\s]+)\+(\d+)$/m', $pubspec, $package);
        preg_match('/appBuild\s*=.*defaultValue:\s*(\d+)/s', $environment, $header);

        $this->assertSame(config('mobile.version'), $package[1] ?? null);
        $this->assertSame(config('mobile.build'), isset($package[2]) ? (int) $package[2] : null);
        $this->assertSame(config('mobile.build'), isset($header[1]) ? (int) $header[1] : null);
    }

    #[Test]
    public function the_release_manifest_matches_the_public_mobile_version(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('downloads/release.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(config('mobile.version'), $manifest['version']);
        $this->assertSame(config('mobile.build'), $manifest['build']);
        $this->assertSame('/download/android', $manifest['apk']['download_path']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['apk']['sha256']);

        /*
         * الحزمة (AAB) لا تُبنى مع كل إصدار — إصدار 1.0.7 شُحن كـAPK وحده،
         * فحذف قسمها من المانيفست كان صوابًا لا نقصًا. لذلك تُفحص إن أُعلنت
         * فقط: اشتراطها دائمًا يدفع إلى إعلان بصمة حزمة قديمة لتمرير الاختبار،
         * وهي أسوأ من غيابها لأن من يتحقّق قبل التثبيت يجدها لا تطابق.
         */
        if (isset($manifest['aab'])) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['aab']['sha256']);
        }
    }

    /**
     * البصمة تصف الملف الموجود فعلًا، لا سلسلة بالشكل الصحيح.
     *
     * فحص الشكل وحده يمرّ على بصمة بناءٍ سابق: يُعاد البناء، ويُنسخ الملف،
     * ويبقى المانيفست يعلن بصمة نسخة لم تعد موجودة. من يتحقّق قبل التثبيت —
     * وهو الغرض الوحيد من نشر البصمة — يجدها لا تطابق فيظنّ الملف مُلاعَبًا.
     *
     * الملف نفسه خارج git (`.gitignore`)، فغيابه تخطٍّ معلن لا فشل صامت.
     */
    #[Test]
    public function the_published_hash_describes_the_published_file(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('downloads/release.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $apk = config('mobile.apk_path');

        if (! is_string($apk) || ! is_file($apk)) {
            $this->markTestSkipped('لا نسخة APK محليًّا — الملف خارج git.');
        }

        $this->assertSame(
            hash_file('sha256', $apk),
            $manifest['apk']['sha256'],
            'بصمة المانيفست لا تطابق الملف المنشور — أُعيد البناء ولم يُحدَّث المانيفست.',
        );

        $this->assertSame((int) filesize($apk), $manifest['apk']['size_bytes']);
    }

    #[Test]
    public function the_public_manifest_reports_the_signed_android_build(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ksgrowth-apk-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'signed-apk-fixture');
        config(['mobile.apk_path' => $path]);

        try {
            $this->getJson(route('api.v1.public.mobile-app'))
                ->assertOk()
                ->assertJsonPath('data.available', true)
                ->assertJsonPath('data.version', config('mobile.version'))
                ->assertJsonPath('data.build', config('mobile.build'))
                ->assertJsonPath('data.android_package', 'net.khaledsaad.ksgrowth_mobile')
                ->assertJsonPath('data.ios_bundle', 'net.khaledsaad.ksgrowthMobile')
                ->assertJsonPath('data.sha256', hash_file('sha256', $path));

            $this->get(route('mobile.download'))
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.android.package-archive');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_download_route_is_not_advertised_as_available_without_an_apk(): void
    {
        config(['mobile.apk_path' => storage_path('app/missing-release.apk')]);

        $this->getJson(route('api.v1.public.mobile-app'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.download_url', null);

        $this->get(route('mobile.download'))->assertNotFound();
    }
}
