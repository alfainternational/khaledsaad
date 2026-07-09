<?php

namespace Tests\Unit\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\EmailSequenceSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\SocialContentSpecialist;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmailAndSocialSpecialistsTest extends TestCase
{
    #[Test]
    public function email_flags_missing_subject_and_cta(): void
    {
        $r = (new EmailSequenceSpecialist)->analyze('', 'شكراً لاهتمامك بخدماتنا التسويقية.');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('no_subject', $codes);
        $this->assertContains('no_cta', $codes);
        $this->assertLessThan(70, $r['score']);
    }

    #[Test]
    public function email_flags_spam_signal(): void
    {
        $r = (new EmailSequenceSpecialist)->analyze('اربح فرصة العمر', 'سجّل الآن للحصول على العرض.');

        $this->assertContains('spam_signal', array_column($r['findings'], 'code'));
    }

    #[Test]
    public function strong_email_scores_high(): void
    {
        $r = (new EmailSequenceSpecialist)->analyze(
            'خطتك التسويقية جاهزة',
            'أعددنا لك ملخصاً عملياً لخطوتك التالية. احجز مكالمة لمراجعته معك.',
        );

        $this->assertGreaterThanOrEqual(85, $r['score']);
    }

    #[Test]
    public function social_flags_length_over_platform_limit(): void
    {
        $long = str_repeat('كلمة ', 100); // ~500 chars > twitter 280
        $r = (new SocialContentSpecialist)->analyze($long, 'twitter');

        $this->assertContains('too_long', array_column($r['findings'], 'code'));
    }

    #[Test]
    public function social_flags_missing_cta_and_hashtags(): void
    {
        $r = (new SocialContentSpecialist)->analyze('منشور عادي بلا أي عناصر تفاعلية واضحة هنا.', 'instagram');
        $codes = array_column($r['findings'], 'code');

        $this->assertContains('no_hashtags', $codes);
        $this->assertContains('no_cta', $codes);
    }

    #[Test]
    public function strong_social_post_scores_well(): void
    {
        $r = (new SocialContentSpecialist)->analyze(
            'هل تخسر عملاءك عند الدفع؟ 3 أخطاء تكلّفك مبيعات. تابعنا للحل. #تسويق #مبيعات',
            'instagram',
        );

        $this->assertGreaterThanOrEqual(85, $r['score']);
    }

    #[Test]
    public function empty_social_post_is_flagged(): void
    {
        $r = (new SocialContentSpecialist)->analyze('   ');

        $this->assertSame(0, $r['score']);
        $this->assertSame('empty', $r['findings'][0]['code']);
    }
}
