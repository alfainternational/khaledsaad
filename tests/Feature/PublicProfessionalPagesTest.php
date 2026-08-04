<?php

namespace Tests\Feature;

use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicProfessionalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_profile_is_complete_crawlable_and_uses_verified_data(): void
    {
        $this->assertTrue(Route::has('profile'));

        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('السيرة المهنية')
            ->assertSee('مدير التسويق')
            ->assertSee('شركة الشمال التعليمية')
            ->assertSee('Hoopoespark')
            ->assertSee('تطوير وتنفيذ استراتيجيات تسويق رقمي متكاملة')
            ->assertSee('جامعة النيلين')
            ->assertSee('إدارة المشاريع الاحترافية PMP')
            ->assertSee('تحسين نتائج محركات البحث SEO')
            ->assertSee('application/ld+json', false)
            ->assertSee('"hasOccupation"', false)
            ->assertSee(config('brand.professional_headline'))
            ->assertSee('https://www.linkedin.com/in/khaledaasaad/', false)
            ->assertSee(route('profile.pdf'), false)
            ->assertDontSee('تاريخ الميلاد')
            ->assertDontSee('عدد المتابعين');
    }

    public function test_each_homepage_topic_has_an_independent_public_page(): void
    {
        Content::query()->create([
            'title' => 'مادة معرفية منشورة',
            'slug' => 'published-knowledge-item',
            'type' => Content::TYPE_ARTICLE,
            'excerpt' => 'ملخص المادة المعرفية المنشورة.',
            'body_html' => '<p>النص</p>',
            'status' => Content::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $pages = [
            'services' => 'المشكلات والمخرجات',
            'methodology' => 'منهجية العمل',
            'principles' => 'مبادئ العمل',
            'knowledge' => 'المعرفة والمحتوى',
            'faq' => 'الأسئلة الشائعة',
            'sample-report' => 'نموذج النتيجة',
        ];

        foreach ($pages as $route => $heading) {
            $this->assertTrue(Route::has($route));
            $this->get(route($route))->assertOk()->assertSee($heading);
        }
    }

    public function test_profile_pdf_and_sitemap_expose_the_new_public_surfaces(): void
    {
        $this->assertTrue(Route::has('profile.pdf'));

        $this->get(route('profile.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $sitemap = $this->get(route('sitemap'))->assertOk();

        foreach (['profile', 'services', 'methodology', 'principles', 'knowledge', 'faq', 'sample-report'] as $route) {
            $sitemap->assertSee(route($route), false);
        }
    }
}
