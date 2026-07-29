<?php

namespace Tests\Feature;

use App\Models\ToolRun;
use App\Models\ToolVersion;
use App\Modules\Diagnosis\GuestPreview;
use App\Services\Guests\GuestSessionManager;
use App\Services\Tools\AnswerCompleteness;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حدّ المستوى ٠: الدرجة + ثلاث فجوات بالاسم دون الحل + لا شيء غير ذلك.
 *
 * بوابة قبول المرحلة ٢. الحدّ ليس تفضيلًا تحريريًّا بل قرار إيراد محسوم
 * (§٢ بند ١٥): الفجوة المعروضة بلا حلّها تخلق سببًا للاشتراك، وعرض حلّها
 * يلغي السبب. اختبار يحرسه أصدق من انضباط موزَّع على القوالب.
 */
class LevelZeroBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function the_preview_names_gaps_and_carries_no_remedy(): void
    {
        $version = ToolVersion::whereRelation('tool', 'key', 'marketing-score')->firstOrFail();

        $preview = app(GuestPreview::class)->build($version, [
            'business_model' => 'services',
            'value_proposition' => '',
            'audience_clarity' => 'none',
        ]);

        $this->assertLessThanOrEqual(GuestPreview::TEASER_LIMIT, count($preview['gaps']));

        foreach ($preview['gaps'] as $gap) {
            // البند اسمٌ فقط. أي مفتاح آخر هنا هو تسريب مهما بدا بريئًا.
            $this->assertSame(['label'], array_keys($gap));
        }
    }

    #[Test]
    public function the_score_never_travels_without_its_basis(): void
    {
        $version = ToolVersion::whereRelation('tool', 'key', 'marketing-score')->firstOrFail();
        $preview = app(GuestPreview::class)->build($version, ['business_model' => 'services']);

        // §١٣: كل رقم يُعرض معه أساسه. «٤٨» وحدها لا تعني شيئًا يمكن تصديقه.
        $this->assertArrayHasKey('basis_count', $preview);
        $this->assertGreaterThan(0, $preview['basis_count']);
    }

    #[Test]
    public function a_complete_business_has_no_manufactured_gaps(): void
    {
        $version = ToolVersion::whereRelation('tool', 'key', 'marketing-score')->firstOrFail();

        $rules = $version->scoring_rules['rules'] ?? [];
        $answers = [];

        foreach ($rules as $rule) {
            $answers[$rule['field']] = match ($rule['type'] ?? 'present') {
                'map' => array_key_last($rule['map'] ?? ['x' => 1]),
                'count' => array_fill(0, (int) ($rule['target'] ?? 1), 'قيمة'),
                default => 'قيمة مكتوبة',
            };
        }

        $preview = app(GuestPreview::class)->build($version, $answers);

        // من لا فجوة لديه لا تُخترع له فجوة لدفعه إلى الاشتراك.
        $this->assertSame([], $preview['gaps']);
    }

    #[Test]
    public function the_result_page_shows_the_score_without_the_fix(): void
    {
        $run = $this->completedTrial();

        $response = $this->get(route('try.result', $run))->assertOk();

        $response->assertSee('درجتك الأولية');
        $response->assertSee('أكبر ثلاث فجوات عندك');

        /*
         * نصوص العلاج تسكن في `weak_advice.recommendation` داخل قواعد الأداة.
         * ظهور أيٍّ منها في صفحة الزائر يعني أن المستوى ٠ صار المستوى ١ مجانًا.
         */
        foreach ($this->remedyTexts($run) as $remedy) {
            $response->assertDontSee($remedy);
        }
    }

    /**
     * @return array<int, string>
     */
    private function remedyTexts(ToolRun $run): array
    {
        $texts = [];

        foreach ($run->toolVersion->scoring_rules['rules'] ?? [] as $rule) {
            foreach (['recommendation', 'description'] as $field) {
                $text = $rule['weak_advice'][$field] ?? null;

                if (is_string($text) && $text !== '') {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    private function completedTrial(): ToolRun
    {
        $response = $this->post(route('try.start', 'marketing-score'));
        $cookie = $response->getCookie(GuestSessionManager::COOKIE, false);

        if ($cookie !== null) {
            $plain = CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
            $this->withCookie(GuestSessionManager::COOKIE, $plain);
        }

        $run = ToolRun::firstOrFail();

        /*
         * تُملأ كل خطوة بما هو مرئي فيها فعلًا لا بقائمة ثابتة: الأسئلة تكيفية،
         * وقائمة مكتوبة بخط اليد تتعفّن مع أول تعديل على الأداة فيسقط الاختبار
         * لسبب لا علاقة له بما يحرسه.
         */
        $steps = $run->toolVersion->fields->pluck('step')->unique()->sort()->values();

        foreach ($steps as $step) {
            $this->post(route('try.step.save', [$run, $step]), $this->answersForStep($run, (int) $step));
        }

        return $run->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function answersForStep(ToolRun $run, int $step): array
    {
        $completeness = app(AnswerCompleteness::class);
        $visible = $completeness->visibleFields($run->toolVersion, $completeness->contextualAnswers($run));

        $answers = [];

        foreach ($visible->where('step', $step) as $field) {
            $options = collect($field->options ?? [])->pluck('value')->filter()->all();

            $answers[$field->key] = match ($field->type) {
                'multiselect' => array_slice($options, 0, 2),
                'select' => $options[0] ?? 'none',
                'number' => 5000,
                default => 'قيمة مكتوبة بطول كافٍ ليقبلها التحقق من المدخلات.',
            };
        }

        return $answers;
    }
}
