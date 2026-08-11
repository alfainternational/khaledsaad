<?php

namespace Tests\Unit;

use Tests\TestCase;

class PdfBrandingContractTest extends TestCase
{
    public function test_shared_arabic_pdf_engine_draws_the_site_identity_on_every_generated_page(): void
    {
        $engine = file_get_contents(app_path('Modules/Reporting/ArabicPdfEngine.php'));

        $this->assertStringContainsString('SetHTMLHeader', $engine);
        $this->assertSame(2, substr_count($engine, 'SetHTMLHeader'));
        $this->assertStringContainsString("'mirrorMargins' => true", $engine);
        $this->assertStringContainsString('khaled-saad-light.png', $engine);
        $this->assertStringContainsString('config(\'app.url\')', $engine);
        $this->assertStringContainsString('border-top:3px solid #2575ff', $engine);
    }

    public function test_profile_pdf_uses_the_shared_arabic_engine_instead_of_dompdf(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Site/ProfilePdfController.php'));

        $this->assertStringContainsString('ArabicPdfEngine', $controller);
        $this->assertStringNotContainsString('DomPDF', $controller);
        /*
         * العنوان يمرّ بالترجمة الآن. القالب `site.pages.profile-pdf` قالب
         * Blade يُغلَّف تلقائيًّا، فكان المحتوى يُترجَم بينما عنوان المستند
         * واسم الملف يبقيان عربيّين — مستندٌ نصفه بلغة ونصفه بأخرى.
         */
        $this->assertStringContainsString("make(__('السيرة المهنية')", $controller);
    }
}
