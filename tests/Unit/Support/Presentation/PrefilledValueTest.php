<?php

namespace Tests\Unit\Support\Presentation;

use App\Models\ToolField;
use App\Support\Presentation\ToolPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مفتاح الحقل قد يتكرر بين أداتين بمعنى مختلف («active_channels» قائمة قنوات
 * هنا، وسؤال عن عددها هناك). القيمة المستعارة لا تصلح حينها، وإن اعتُبرت
 * «معروفة» طُويت في صندوق مغلق فمنعت الإرسال بلا رسالة — وهذا ما نحرسه.
 */
class PrefilledValueTest extends TestCase
{
    #[Test]
    public function a_value_outside_the_options_is_dropped_and_not_treated_as_known(): void
    {
        $field = $this->field('active_channels', 'select', [
            ['value' => 'none', 'label' => 'ولا واحدة'],
            ['value' => 'one_two', 'label' => 'واحدة أو اثنتان'],
        ]);

        // قيمة مستعارة من أداة أخرى لا تنتمي لخيارات هذا السؤال.
        $result = app(ToolPresenter::class)->field($field, ['active_channels' => 'seo'], ['active_channels']);

        $this->assertNull($result['value'], 'القيمة غير الصالحة يجب أن تُسقط.');
        $this->assertFalse($result['is_known'], 'الحقل الفارغ لا يُخفى كمعروف، بل يُسأل عنه ظاهرًا.');
    }

    #[Test]
    public function a_multiselect_keeps_only_the_options_that_exist_here(): void
    {
        $field = $this->field('channels', 'multiselect', [
            ['value' => 'seo', 'label' => 'البحث'],
            ['value' => 'social', 'label' => 'التواصل'],
        ]);

        $result = app(ToolPresenter::class)->field($field, ['channels' => ['seo', 'tiktok', 'social']], ['channels']);

        // نُبقي الصالح ونُسقط ما لا وجود له، بدل إلغاء الاختيار كله.
        $this->assertSame(['seo', 'social'], $result['value']);
        $this->assertTrue($result['is_known']);
    }

    #[Test]
    public function a_valid_prefilled_value_stays_known_so_we_do_not_ask_twice(): void
    {
        $field = $this->field('active_channels', 'select', [
            ['value' => 'none', 'label' => 'ولا واحدة'],
            ['value' => 'many', 'label' => 'كثيرة'],
        ]);

        $result = app(ToolPresenter::class)->field($field, ['active_channels' => 'many'], ['active_channels']);

        $this->assertSame('many', $result['value']);
        $this->assertTrue($result['is_known']);
    }

    #[Test]
    public function a_question_without_custom_help_receives_guidance_that_matches_its_answer_type(): void
    {
        $text = app(ToolPresenter::class)->field($this->field('message', 'textarea', []), [], []);
        $single = app(ToolPresenter::class)->field($this->field('priority', 'select', [
            ['value' => 'one', 'label' => 'الأول'],
        ]), [], []);
        $multiple = app(ToolPresenter::class)->field($this->field('channels', 'multiselect', [
            ['value' => 'search', 'label' => 'البحث'],
        ]), [], []);

        $this->assertSame('اكتب إجابتك بطريقتك، ويمكنك استخدام مثال من واقع مشروعك.', $text['help']);
        $this->assertSame('اختر الإجابة الأقرب إلى وضع مشروعك الآن.', $single['help']);
        $this->assertSame('اختر كل ما ينطبق على وضع مشروعك الآن.', $multiple['help']);
    }

    /**
     * @param  array<int, array<string, string>>  $options
     */
    private function field(string $key, string $type, array $options): ToolField
    {
        return new ToolField([
            'key' => $key,
            'label' => 'سؤال',
            'type' => $type,
            'options' => $options,
            'required' => true,
            'step' => 1,
        ]);
    }
}
