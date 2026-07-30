<?php

namespace App\Modules\Diagnosis;

use App\Models\AnswerFitness;
use App\Models\Project;

/**
 * قراءة درجات كفاية المدخلات من قاعدة البيانات — بلا اتصال شبكي.
 *
 * القياس يجري في `Intake` عند الإجابة، والحساب يقرؤه هنا. الفصل ليس تنظيمًا:
 * §٨ تمنع أي اتصال شبكي داخل `Diagnosis` لأن الدرجة يجب أن تكون قابلة لإعادة
 * الإنتاج من لقطة قاعدة بيانات. لو قاست هذه الوحدة الكفاية بنفسها عبر نموذج
 * لغوي لاختلفت درجتان بنفس المدخلات، وانهارت المقارنة الزمنية التي تقوم عليها
 * التنبيهات.
 *
 * الحتميّ وحده يُقرأ: مصدر `assist` رأيُ نموذج لغوي، إرشاديٌّ للمستخدم ولا
 * يدخل معادلة درجة.
 */
class InputFitnessReader
{
    /**
     * @return array<string, AnswerFitness>  مفتاحه `field_key`
     */
    public function forProject(Project $project): array
    {
        return AnswerFitness::query()
            ->where('project_id', $project->id)
            ->where('source', AnswerFitness::SOURCE_DETERMINISTIC)
            ->get()
            ->keyBy('field_key')
            ->all();
    }
}
