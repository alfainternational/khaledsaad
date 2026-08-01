<?php

namespace App\Modules\Intake\Engine;

use App\Models\AnswerFitness;
use App\Models\ConsultationSession;
use App\Models\ModuleQuestion;
use App\Models\ProjectAnswer;
use App\Models\QuestionVersion;
use App\Services\Tools\ProjectContextResolver;
use Illuminate\Support\Collection;

class NextQuestionSelector
{
    public function __construct(
        private readonly QuestionPriority $priority,
        private readonly ProjectContextResolver $context,
    ) {}

    public function next(ConsultationSession $session): ?QuestionVersion
    {
        $limit = (int) data_get($session->blueprintVersion->settings, "depth_limits.{$session->depth}", 35);
        if ($session->questions_answered >= $limit) {
            return null;
        }

        $answered = $session->answers()->pluck('question_version_id');
        $activeModules = $session->moduleStates()->whereIn('state', ['core', 'supporting'])->pluck('diagnostic_module_id');
        $known = ProjectAnswer::where('project_id', $session->project_id)->pluck('value_json', 'field_key')
            ->map(fn ($value) => $value['value'] ?? null)->all();

        /*
         * مفاتيح project.* المحجوزة تُدمج مع الإجابات كما في مسار الأداة
         * الكلاسيكي (AnswerCompleteness::contextualAnswers): شروط القطاع ونوع
         * البيع تُقيَّم بنفس الدلالات في المسارين، ولا تتصادم مع متغيرات
         * الأسئلة لأن المحلّل يحجز أسماءها.
         */
        $known = array_merge($known, $this->context->for($session->project));

        $bindings = ModuleQuestion::query()
            ->with(['questionVersion.definition', 'blueprintModule.module'])
            ->whereHas('blueprintModule', fn ($query) => $query
                ->where('blueprint_version_id', $session->blueprint_version_id)
                ->whereIn('diagnostic_module_id', $activeModules))
            ->get();

        /*
         * عجز الكفاية يُحسب على **كل** أسئلة الوحدة قبل تصفية المُجاب عنها: هو
         * صفةُ ما أُجيب لا صفةُ ما بقي. حسابه بعد التصفية كان سيعطي صفرًا دائمًا
         * لأن ما أُجيب عنه يكون قد خرج من المجموعة.
         */
        $deficits = $this->deficits($bindings, $session->project_id);

        return $bindings
            ->whereNotIn('question_version_id', $answered)
            ->filter(function (ModuleQuestion $binding) use ($known): bool {
                $variable = $binding->questionVersion->definition->internal_variable;

                return ! array_key_exists($variable, $known) || $known[$variable] === null || $known[$variable] === '' || $known[$variable] === [];
            })
            ->filter(fn (ModuleQuestion $binding) => $this->visible($binding->show_when, $known))
            ->sortByDesc(fn (ModuleQuestion $binding) => [
                $this->priority->score($binding, false, $deficits[$binding->blueprint_module_id] ?? 0.0),
                -$binding->sort_order,
                $binding->questionVersion->definition->key,
            ])
            ->first()?->questionVersion;
    }

    /**
     * نسبة المدخلات المُجاب عنها إجابةً غير كافية، لكل وحدة.
     *
     * تُقرأ من `answer_fitness` الذي كتبته طبقة القياس عند الإجابة. لا حساب هنا:
     * الحساب في موضع واحد، وتكراره في المحرّك كان سينتج رقمين لنفس الإجابة.
     *
     * @param  Collection<int, ModuleQuestion>  $bindings
     * @return array<int, float> مفتاحه `blueprint_module_id`
     */
    private function deficits(Collection $bindings, int $projectId): array
    {
        $fitness = AnswerFitness::query()
            ->where('project_id', $projectId)
            ->where('source', AnswerFitness::SOURCE_DETERMINISTIC)
            ->get()
            ->keyBy('field_key');

        if ($fitness->isEmpty()) {
            return [];
        }

        $deficits = [];

        foreach ($bindings->groupBy('blueprint_module_id') as $moduleId => $group) {
            $measured = 0;
            $weak = 0;

            foreach ($group as $binding) {
                $score = $fitness->get($binding->questionVersion->definition->internal_variable);

                if ($score === null) {
                    continue;
                }

                $measured++;

                if ($score->verdict !== AnswerFitness::VERDICT_SUFFICIENT) {
                    $weak++;
                }
            }

            if ($measured > 0 && $weak > 0) {
                $deficits[(int) $moduleId] = $weak / $measured;
            }
        }

        return $deficits;
    }

    private function visible(?array $rules, array $known): bool
    {
        if ($rules === null || $rules === []) {
            return true;
        }

        foreach ($rules as $key => $expected) {
            $allowed = is_array($expected) ? $expected : [$expected];
            $actual = $known[$key] ?? null;

            $excluded = [];
            $required = [];
            foreach ($allowed as $value) {
                if (is_string($value) && str_starts_with($value, '!')) {
                    $excluded[] = substr($value, 1);

                    continue;
                }
                $required[] = $value;
            }

            if (in_array($actual, $excluded, true)) {
                return false;
            }

            if ($required === [] || in_array($actual, $required, true)) {
                continue;
            }

            /*
             * مفتاح project.* قيمته من محلّل السياق لا من إجابة منتظرة: غيابه
             * قرارٌ (لا نخمّن نوع البيع) فيُخفي السؤال — نفس دلالات
             * ToolField::isVisible في مسار الأداة. أما مفتاح إجابة لم تصل بعد
             * فيبقى سؤاله ظاهرًا حتى تُعرف قيمته.
             */
            if (str_starts_with((string) $key, 'project.') || $actual !== null) {
                return false;
            }
        }

        return true;
    }
}
