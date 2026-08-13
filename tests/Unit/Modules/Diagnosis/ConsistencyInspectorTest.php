<?php

namespace Tests\Unit\Modules\Diagnosis;

use App\Modules\Diagnosis\ConsistencyInspector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConsistencyInspectorTest extends TestCase
{
    private ConsistencyInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new ConsistencyInspector;
    }

    #[Test]
    public function it_flags_two_answers_that_describe_different_audiences(): void
    {
        $clashes = $this->inspector->inspect([
            'audience' => 'أمهات في الرياض يبحثن عن أنشطة لأطفالهن',
            'best_customer' => 'شركات مقاولات متوسطة تحتاج برامج محاسبة',
        ]);

        $this->assertCount(1, $clashes);
        $this->assertSame('جمهورك', $clashes[0]['subject']);
        $this->assertSame('audience', $clashes[0]['left_key']);
        $this->assertSame('best_customer', $clashes[0]['right_key']);
    }

    #[Test]
    public function it_stays_silent_when_the_same_answer_is_only_reworded(): void
    {
        $clashes = $this->inspector->inspect([
            'audience' => 'أمهات في الرياض يبحثن عن أنشطة تعليمية لأطفالهن',
            'best_customer' => 'أمهات الرياض اللواتي يبحثن عن أنشطة تعليمية لأطفالهن',
        ]);

        $this->assertSame([], $clashes);
    }

    #[Test]
    public function short_vague_answers_are_a_fitness_problem_not_a_contradiction(): void
    {
        // «الجميع» و«الكل» متباعدان رمزيًّا، والحكم عليهما بالتناقض خطأ:
        // مشكلتهما ضعف الكفاية، ويقيسها `input_fitness` لا هذا الفاحص.
        $clashes = $this->inspector->inspect([
            'audience' => 'الجميع',
            'best_customer' => 'الكل',
        ]);

        $this->assertSame([], $clashes);
    }

    #[Test]
    public function a_missing_side_is_a_gap_not_a_clash(): void
    {
        $this->assertSame([], $this->inspector->inspect([
            'audience' => 'أمهات في الرياض يبحثن عن أنشطة لأطفالهن',
        ]));
    }

    #[Test]
    public function it_reads_answers_stored_in_the_run_value_wrapper(): void
    {
        // `ToolRun::answerMap()` يعيد `['value' => …]` لا القيمة مجردة.
        $clashes = $this->inspector->inspect([
            'value_proposition' => ['value' => 'نوصّل في نفس اليوم داخل المدينة'],
            'differentiator' => ['value' => 'أرخص أسعار الاشتراك السنوي للبرمجيات'],
        ]);

        $this->assertCount(1, $clashes);
        $this->assertSame('سبب الشراء منك', $clashes[0]['subject']);
    }

    #[Test]
    public function the_verdict_does_not_change_between_runs(): void
    {
        // حتميّة الفاحص هي سبب وجوده: قياسٌ يعتمد على عيّنة نموذج واحدة
        // يخالف §٤.٢، وهذا يعطي النتيجة نفسها كلما أُعيد.
        $answers = [
            'what_you_sell' => 'دورات تدريبية للأطفال في البرمجة والروبوت',
            'description' => 'مغسلة سيارات متنقلة تخدم أحياء شمال جدة',
        ];

        $this->assertEquals($this->inspector->inspect($answers), $this->inspector->inspect($answers));
        $this->assertCount(1, $this->inspector->inspect($answers));
    }
}
