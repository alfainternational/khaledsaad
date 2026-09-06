<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicResponsiveLayoutTest extends TestCase
{
    public function test_mobile_header_has_a_single_row_and_moves_all_secondary_actions_into_the_menu(): void
    {
        $css = str_replace("\r\n", "\n", file_get_contents(resource_path('css/app.css')));
        $view = file_get_contents(resource_path('views/partials/site-header.blade.php'));
        /*
         * الإرساء على القاعدة نفسها لا على أول ظهور لنقطة التوقّف.
         *
         * بعد توحيد النقاط في أربع (INV-10) صارت كتلٌ عدّة تتشارك
         * `1023px`، فأولُ ظهورٍ لها لم يعد يعني هذه الكتلة. والإرساء على
         * موضعٍ رقمي يكسره أي إعادة ترتيب — بينما القاعدة التي نحرسها
         * هي التي يجب أن تُوجد.
         */
        $rule = strpos($css, '.desktop-nav,
    .nav-actions');
        $this->assertNotFalse($rule, 'قاعدة إخفاء ملاحة سطح المكتب غائبة.');

        $mobileQuery = strrpos($css, '@media (max-width: 1023px)', $rule - strlen($css));

        $this->assertNotFalse($mobileQuery);
        $this->assertLessThan(
            $mobileQuery,
            strrpos($css, "\n.nav-actions {"),
            'The desktop nav-actions rule must be declared before the mobile override.',
        );

        $mobileRules = substr($css, $mobileQuery, 2600);

        $this->assertStringContainsString(".desktop-nav,\n    .nav-actions", $mobileRules);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto;', $mobileRules);
        $this->assertStringContainsString('min-height: 4.25rem;', $mobileRules);
        $this->assertStringContainsString('min-width: 44px;', $mobileRules);
        $this->assertStringContainsString('mobile-menu__utilities', $view);
        $this->assertStringContainsString("@include('partials.theme-toggle')", $view);
    }

    public function test_small_mobile_layout_uses_safe_gutters_and_fluid_text(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        // نقطة التوقّف من التوكنز الأربع (INV-10): كانت 760px قبل توحيدها.
        $smallQuery = strpos($css, '@media (max-width: 767px)');

        $this->assertNotFalse($smallQuery);

        $smallRules = substr($css, $smallQuery, 4200);

        $this->assertStringContainsString('padding-inline: var(--ui-page-inline);', $smallRules);
        $this->assertStringContainsString('width: min(8.5rem, 44vw);', $smallRules);
        $this->assertStringContainsString('padding-block: clamp(3.5rem, 12vw, 4.5rem);', $smallRules);
    }
}
