<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformPagesTest extends TestCase
{
    #[Test]
    public function all_core_platform_pages_are_available(): void
    {
        $pages = [
            '/' => 'منصة التسويق الاستراتيجي',
            '/paths' => 'المسارات',
            '/tools' => 'أدوات التحليل',
            '/studio' => 'الاستوديو الذكي',
            '/templates' => 'القوالب',
            '/reports' => 'التقارير',
            '/projects' => 'المشاريع',
            '/account' => 'الحساب والإعدادات',
            '/agency' => 'وضع الوكالة',
        ];

        foreach ($pages as $uri => $expectedText) {
            $this->get($uri)
                ->assertOk()
                ->assertSee($expectedText);
        }
    }

    #[Test]
    public function admin_route_redirects_guests_to_admin_login(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.login'));
    }
}
