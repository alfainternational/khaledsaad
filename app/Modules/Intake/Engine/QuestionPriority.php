<?php

namespace App\Modules\Intake\Engine;

use App\Models\ModuleQuestion;

class QuestionPriority
{
    /**
     * @param  float  $fitnessDeficit  نسبة مدخلات هذه الوحدة التي أُجيب عنها إجابةً
     *                                 غير كافية (0.0 – 1.0).
     */
    public function score(ModuleQuestion $question, bool $known = false, float $fitnessDeficit = 0.0): float
    {
        if ($known) {
            return 0.0;
        }

        $sensitivity = $question->questionVersion->definition->sensitivity === 'sensitive' ? 2 : 0;
        $uncertainty = $question->critical ? 1.0 : 0.75;

        $base = ($question->diagnostic_impact * $uncertainty * $question->discrimination)
            / max(1, $question->answer_burden + $sensitivity);

        /*
         * الوحدة التي أُجيب عنها إجابات عامة تتقدّم على غيرها.
         *
         * «أجاب» لا يساوي «عرفنا». من وصف جمهوره بـ«الجميع» أعطى تغطية كاملة
         * ومعرفةً شبه صفر، وكان المحرّك ينتقل عنه إلى وحدة أخرى لأن المفتاح صار
         * مملوءًا — فيُبنى التشخيص على أضعف ما عنده ولا يُسأل عنه ثانية.
         *
         * التقدّم هنا لا يعيد السؤال نفسه: يرفع أولوية أسئلة الوحدة **الأخرى**
         * التي لم تُسأل بعد، فيقترب النظام من المعلومة من زاوية ثانية بدل أن
         * يكرّر سؤالًا فشل مرة.
         *
         * الحدّ الأقصى للمضاعفة مرة واحدة (×2): وحدةٌ ضعيفة تتقدّم، ولا تحتكر
         * ما بقي من سقف الأسئلة فتُترك بقية المحاور بلا مدخل واحد.
         */
        return $base * (1.0 + max(0.0, min(1.0, $fitnessDeficit)));
    }
}
