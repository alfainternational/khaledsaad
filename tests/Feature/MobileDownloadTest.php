<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileDownloadTest extends TestCase
{
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
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $manifest['aab']['sha256']);
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
