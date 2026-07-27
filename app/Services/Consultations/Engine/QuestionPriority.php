<?php

namespace App\Services\Consultations\Engine;

use App\Models\ModuleQuestion;

class QuestionPriority
{
    public function score(ModuleQuestion $question, bool $known = false): float
    {
        if ($known) {
            return 0.0;
        }

        $sensitivity = $question->questionVersion->definition->sensitivity === 'sensitive' ? 2 : 0;
        $uncertainty = $question->critical ? 1.0 : 0.75;

        return ($question->diagnostic_impact * $uncertainty * $question->discrimination)
            / max(1, $question->answer_burden + $sensitivity);
    }
}
