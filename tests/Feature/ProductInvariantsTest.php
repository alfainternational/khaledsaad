<?php

namespace Tests\Feature;

use App\Exceptions\AIProviderException;
use App\Exceptions\BillingLimitException;
use App\Support\Experience\Experience;
use App\Support\Failures\FailureClassifier;
use App\Support\Failures\FailureKind;
use App\Support\Failures\RunFailure;
use App\Support\Failures\RunFailureAction;
use App\Support\Navigation\NavRegistry;
use App\Support\Presentation\Num;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * بوابات ضد فئات الأعطال، لا ضد حالاتها.
 *
 * كل اختبار هنا يمنع صنفًا كاملًا من العطل: إصلاحُ الحالة وحدها يعيدها
 * بعد شهرين بشكل جديد. هذا هو الفرق بين إصلاحٍ وبوابة.
 */
class ProductInvariantsTest extends TestCase
{
    /**
     * INV-8: عطلٌ لدينا لا يحمل إجراءً مطلوبًا من المستخدم أبدًا.
     */
    #[Test]
    public function a_failure_that_is_ours_can_never_carry_a_user_action(): void
    {
        $this->expectException(\LogicException::class);

        new RunFailure(
            kind: FailureKind::Ours,
            code: 'provider_unavailable',
            title: 'عطل لدينا',
            message: 'رسالة',
            userAction: new RunFailureAction(label: 'اشحن رصيدك', route: 'app.billing'),
        );
    }

    /**
     * INV-8: نفاد اشتراك المزوّد يُصنَّف عطلًا لنا، ولا يذكر رصيد المستخدم.
     * هذا هو العطل الذي جعل شاشةً تقول «لديك 0» بينما الفوترة تقول «99967».
     */
    #[Test]
    public function a_provider_outage_is_never_worded_as_the_users_credit(): void
    {
        foreach ([402, 429, 500, null] as $status) {
            $failure = (new FailureClassifier)->classify(new AIProviderException('DeepSeek', $status));

            $this->assertSame(FailureKind::Ours, $failure->kind, "الحالة {$status}");
            $this->assertNull($failure->userAction);
            $this->assertStringNotContainsString('رصيدك غير كافٍ', $failure->message);
            $this->assertStringNotContainsString('تحتاج', $failure->message);
            // ولا يتسرّب اسم المزوّد ولا أي مصطلح داخلي إلى الشاشة.
            $this->assertStringNotContainsString('DeepSeek', $failure->title.$failure->message);
        }
    }

    /**
     * الحدّ الذي يملك المستخدم رفعه هو وحده الذي يحمل إجراءً.
     */
    #[Test]
    public function only_a_users_own_limit_asks_the_user_to_act(): void
    {
        $failure = (new FailureClassifier)->classify(BillingLimitException::credits(5, 0));

        $this->assertSame(FailureKind::Theirs, $failure->kind);
        $this->assertNotNull($failure->userAction);
        $this->assertSame('app.billing', $failure->userAction->route);
    }

    /**
     * B5: العربية تصرّف المعدود؛ «تحتاج 5 رصيدًا» كانت تتكرر ١١ مرة.
     */
    #[Test]
    public function credit_counts_use_arabic_pluralisation(): void
    {
        $this->assertSame('لا رصيد', Num::credits(0));
        $this->assertSame('رصيد واحد', Num::credits(1));
        $this->assertSame('رصيدان', Num::credits(2));
        $this->assertSame('5 أرصدة', Num::credits(5));
        $this->assertSame('40 رصيدًا', Num::credits(40));
    }

    #[Test]
    public function the_insufficient_credit_message_never_glues_a_number_to_a_singular_noun(): void
    {
        $message = BillingLimitException::credits(5, 0)->getMessage();

        $this->assertStringNotContainsString('5 رصيدًا', $message);
        $this->assertStringContainsString('5 أرصدة', $message);
    }

    /**
     * INV-6: كل عنصر ملاحة يشير إلى مسار موجود.
     */
    #[Test]
    public function every_navigation_item_points_at_a_registered_route(): void
    {
        foreach (Experience::cases() as $experience) {
            foreach (NavRegistry::primary($experience) as $item) {
                if ($item->route === null) {
                    continue; // عنصر «قريبًا» معلن، ولا يدّعي وجهة.
                }

                $this->assertTrue(
                    Route::has($item->route),
                    "عنصر الملاحة «{$item->label}» يشير إلى مسار غير مسجَّل: {$item->route}",
                );
            }
        }
    }

    /**
     * INV-6 / B1: ممنوع أن يوجّه قسمان مختلفان إلى الوجهة نفسها.
     * «مشاريعي» و«الخطة والمهام» و«التقارير» كانت ثلاثتها `projects.index`.
     */
    #[Test]
    public function no_two_navigation_items_share_one_destination(): void
    {
        foreach (Experience::cases() as $experience) {
            $routes = collect(NavRegistry::primary($experience))
                ->filter(fn ($item) => $item->isAvailable())
                ->map(fn ($item) => $item->route)
                ->all();

            $this->assertSame(
                array_values(array_unique($routes)),
                array_values($routes),
                "قسمان في تجربة «{$experience->value}» يشيران إلى المسار نفسه.",
            );
        }
    }
}
