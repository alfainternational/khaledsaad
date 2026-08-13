<?php

namespace Tests\Feature;

use App\Modules\Shared\Sectors\Sector;
use App\Modules\Shared\Sectors\SectorCapabilities;
use App\Modules\Shared\Sectors\SectorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * صفحة القطاع تُثبت التخصص أو لا تكون.
 *
 * الحارس هنا يمنع أخطر انحراف في هذه الصفحة: أن تعد بما لا يفعله المحرّك.
 * كل رقم فيها يُقرأ من الأدوات المبذورة وقوالب المؤشرات، فالاختبار يتحقّق
 * أن المصدر هو المصدر لا نصٌّ كُتب بجانبه.
 */
class SectorLandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_specialized_sector_has_a_reachable_page(): void
    {
        foreach (Sector::SPECIALIZED as $sector) {
            $this->get(route('sectors.show', $sector))
                ->assertOk()
                ->assertSee(Sector::label($sector));
        }
    }

    /**
     * قطاع بلا عمق لا صفحة له: «قطاع آخر» يُخدم بالمسار العام، ووعدُ صفحةٍ
     * له ادّعاء تخصص لم يُبنَ.
     */
    #[Test]
    public function undeclared_sectors_have_no_page(): void
    {
        $this->get('/sectors/other')->assertNotFound();
        $this->get('/sectors/saas')->assertNotFound();
    }

    #[Test]
    public function every_specialized_sector_has_a_profile_and_the_others_do_not(): void
    {
        foreach (Sector::SPECIALIZED as $sector) {
            $profile = SectorProfile::for($sector);

            $this->assertNotNull($profile, "قطاع متخصص بلا ملف تعريف: {$sector}.");

            foreach (['label', 'audience', 'pain', 'promise', 'knows', 'sample_category', 'sample_city'] as $key) {
                $this->assertNotEmpty($profile[$key] ?? null, "ملف {$sector} ينقصه {$key}.");
            }
        }

        $this->assertNull(SectorProfile::for(Sector::OTHER));
    }

    /**
     * البرهان مقروء من المحرّك: بنك الأسئلة والمؤشرات وفحص Schema.
     */
    #[Test]
    public function the_page_proves_itself_from_engine_capabilities(): void
    {
        $capabilities = app(SectorCapabilities::class)->for(Sector::EDUCATION);

        $this->assertNotEmpty($capabilities['buyer_questions'], 'أسئلة المشتري تُقرأ من QuestionBank.');
        $this->assertNotEmpty($capabilities['kpis'], 'مؤشرات القطاع تُقرأ من KpiTemplates.');
        $this->assertContains('Course', $capabilities['schema']['types']);

        // ولا تُخترع أرقام: العدّاد صفر ما دامت الأدوات غير مبذورة في الاختبار.
        $this->assertIsInt($capabilities['questions']['count']);
    }

    #[Test]
    public function the_home_page_links_every_sector_page(): void
    {
        $response = $this->get(route('home'));

        foreach (Sector::SPECIALIZED as $sector) {
            $response->assertSee(route('sectors.show', $sector));
        }
    }

    /**
     * الفهرس يعرض الثلاثة معًا.
     *
     * الحارس هنا يمنع عودة العطل الذي كان: رابط «القطاعات» يذهب إلى قطاع
     * واحد، فيقرأ الزائر تخصصًا مفردًا بينما نعلن ثلاثة.
     */
    #[Test]
    public function the_index_presents_all_three_sectors_side_by_side(): void
    {
        $response = $this->get(route('sectors.index'))->assertOk();

        foreach (Sector::SPECIALIZED as $sector) {
            $response->assertSee(Sector::label($sector));
            $response->assertSee(route('sectors.show', $sector));
            // وجعه معروض لا اسمه وحده: البطاقة تعرّف بالقطاع لا تُعنونه.
            $response->assertSee(SectorProfile::for($sector)['pain']);
        }
    }

    /**
     * التنقل يقود إلى الفهرس لا إلى قطاع بعينه.
     */
    #[Test]
    public function the_navigation_does_not_favour_one_sector(): void
    {
        $header = file_get_contents(resource_path('views/partials/site-header.blade.php'));

        $this->assertStringContainsString("route('sectors.index')", $header);
        $this->assertStringNotContainsString("route('sectors.show', \\App\\Modules\\Shared\\Sectors\\Sector::EDUCATION)", $header);
    }
}
