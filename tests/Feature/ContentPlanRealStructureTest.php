<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Content\ContentPlanDocxImporter;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentPlanRealStructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reads_split_runs_and_alternating_label_value_rows_like_the_supplied_document(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'شركة الشمال التعليمية']);
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('خطة المحتوى الرقمي لشهر أغسطس 2026م');
        $table = $section->addTable();

        $header = $table->addRow()->addCell()->addTextRun();
        foreach (['منشور', '٠١', '·', 'الاثنين ٣ أغسطس', '٢٠٢٦', '·', 'الموقع والأثر'] as $part) {
            $header->addText($part);
        }

        $table->addRow()->addCell()->addText('نص منشور X (تويتر)');
        $table->addRow()->addCell()->addText('التوطين يبدأ بالتأهيل. #الحدود_الشمالية');
        $table->addRow()->addCell()->addText('نص منشور لينكد إن');
        $table->addRow()->addCell()->addText('نسخة مهنية مكتملة لمنشور لينكد إن عن التوطين والتأهيل المحلي.');

        $designLabel = $table->addRow()->addCell()->addTextRun();
        $designLabel->addText('موجز');
        $designLabel->addText('التصميم  للمصمم');
        $table->addRow()->addCell()->addText('الشكل: بطاقة نصية. النص على التصميم: رئيسي: «التوطين يبدأ بالتأهيل»');

        $notesLabel = $table->addRow()->addCell()->addTextRun();
        $notesLabel->addText('ملاحظات');
        $notesLabel->addText('النشر  للناشر');
        $notes = $table->addRow()->addCell()->addTextRun();
        $notes->addText('الهاشتاقات: #الحدود_الشمالية');
        $notes->addText('النص البديل (');
        $notes->addText('Alt');
        $notes->addText('):');
        $notes->addText('بطاقة نصية تحمل عبارة التوطين يبدأ بالتأهيل');
        $notes->addText('ملاحظة نشر: النص قائم بذاته');

        $path = tempnam(sys_get_temp_dir(), 'real-content-plan-').'.docx';
        IOFactory::createWriter($word)->save($path);

        $plan = app(ContentPlanDocxImporter::class)->import($path, $project, $user);
        $post = $plan->posts->firstOrFail();

        $this->assertSame('2026-08-03', $post->publish_at->toDateString());
        $this->assertSame('الموقع والأثر', $post->pillar);
        $this->assertStringContainsString('بطاقة نصية', $post->design_brief);
        $this->assertSame('بطاقة نصية تحمل عبارة التوطين يبدأ بالتأهيل', $post->alt_text);
    }
}
