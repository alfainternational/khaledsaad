<?php

namespace Tests\Feature;

use App\Models\AnswerFitness;
use App\Models\User;
use App\Modules\Reporting\AgencyReportService;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * كفاية المدخل تُقاس على كل مسار كتابة، لا في شاشة الأدوات وحدها.
 *
 * العطل الذي تحرسه هذه الاختبارات: كان قياس الكفاية يجري فقط حين يُكتب الحقل
 * داخل أداة، بينما مالئ ملف المشروع مباشرة أو مُجيب موجز الوكالة يفلت منه —
 * فتأخذ «قيمتي إني الأفضل» معاملًا كاملًا في محور الوضوح الاستراتيجي، و
 * `primary_goal` المضبوط من الموجز لا يبلغ الدماغ الذي يقرأ منه حساب المحور.
 */
class ProfileBriefFitnessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function filling_the_project_profile_directly_measures_its_open_fields(): void
    {
        $user = User::factory()->create();

        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'value_proposition' => 'الأفضل',
        ]);

        // مسار الملف المباشر يقيس الآن كما يقيس مسار الأداة: صار للحقل درجة كفاية.
        $fitness = AnswerFitness::where('project_id', $project->id)
            ->where('field_key', 'value_proposition')
            ->first();

        $this->assertNotNull($fitness, 'القيمة المميزة المكتوبة في الملف يجب أن تُقاس كفايتها.');
        $this->assertSame(AnswerFitness::VERDICT_INSUFFICIENT, $fitness->verdict);
    }

    #[Test]
    public function a_specific_value_proposition_scores_above_a_vague_one_on_the_profile_path(): void
    {
        $user = User::factory()->create();

        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'value_proposition' => 'نوصّل مستلزمات المطاعم خلال ساعتين في الرياض بأسعار الجملة، '
                .'مع فاتورة ضريبية وإرجاع مجاني خلال يومين — ما لا يقدّمه المورّد التقليدي.',
        ]);

        $fitness = AnswerFitness::where('project_id', $project->id)
            ->where('field_key', 'value_proposition')
            ->first();

        $this->assertNotNull($fitness);
        $this->assertSame(AnswerFitness::VERDICT_SUFFICIENT, $fitness->verdict);
    }

    #[Test]
    public function structured_profile_fields_are_not_measured_for_fitness(): void
    {
        $user = User::factory()->create();

        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'primary_goal' => 'awareness',
            'monthly_budget' => 5000,
        ]);

        // الاختيار والرقم كفايتهما صفة السؤال لا الإجابة، فلا يُقاسان
        // وإلا عوقب مدخل صحيح بدرجة بلا معنى.
        $this->assertDatabaseMissing('answer_fitness', [
            'project_id' => $project->id,
            'field_key' => 'primary_goal',
        ]);
        $this->assertDatabaseMissing('answer_fitness', [
            'project_id' => $project->id,
            'field_key' => 'monthly_budget',
        ]);
    }

    #[Test]
    public function the_agency_brief_records_its_goal_into_the_brain_not_only_the_column(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نشاطي']);

        app(AgencyReportService::class)->saveBrief($project, [
            'primary_goal' => 'leads',
            'success_metric' => 'مضاعفة عدد الطلبات الشهرية خلال تسعين يومًا',
        ]);

        // primary_goal المضبوط من الموجز يبلغ إسقاط الدماغ الذي يقرأ منه حساب
        // المحور — قبل الإصلاح كان يبقى في عمود project_profiles وحده فيغيب.
        $this->assertDatabaseHas('project_answers', [
            'project_id' => $project->id,
            'field_key' => 'primary_goal',
        ]);

        // مؤشّر النجاح مدخل مفتوح، فتُقاس كفايته كبقية الاستقبال.
        $this->assertDatabaseHas('answer_fitness', [
            'project_id' => $project->id,
            'field_key' => 'success_metric',
        ]);
    }
}
