<?php

namespace Tests\Feature;

use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailThemeTest extends TestCase
{
    #[Test]
    public function notification_mails_render_with_the_rtl_branded_theme(): void
    {
        $mail = (new MailMessage)
            ->subject('تقريرك جاهز')
            ->greeting('مرحبًا')
            ->line('اكتمل تحليل مشروعك، وتستطيع فتح تقريرك الآن.')
            ->action('افتح التقرير', 'https://khaledsaad.net/app');

        $html = $mail->render()->toHtml();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('اكتمل تحليل مشروعك', $html);
        $this->assertStringContainsString('افتح التقرير', $html);
        // هوية العلامة لا قالب Laravel الافتراضي.
        $this->assertStringContainsString('#2575ff', $html);
        $this->assertStringNotContainsString('All rights reserved', $html);
    }
}
