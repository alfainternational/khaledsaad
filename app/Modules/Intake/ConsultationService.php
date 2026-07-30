<?php

namespace App\Modules\Intake;

use App\Models\AnswerFitness;
use App\Models\ConsultationAnswer;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationConflict;
use App\Models\ConsultationInference;
use App\Models\ConsultationSession;
use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Modules\Brain\ProjectKnowledgeService;
use App\Modules\Intake\Engine\AnswerValidator;
use App\Modules\Intake\Fitness\AnswerFitnessScorer;
use App\Modules\Intake\Engine\ConflictDetector;
use App\Modules\Intake\Engine\ModuleScopeResolver;
use App\Modules\Intake\Engine\NextQuestionSelector;
use App\Services\Tools\FullDiagnosisRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationService
{
    public function __construct(
        private readonly ModuleScopeResolver $scope,
        private readonly NextQuestionSelector $questions,
        private readonly FullDiagnosisRunner $diagnosis,
        private readonly ConflictDetector $conflicts,
        private readonly ConsultationEventRecorder $events,
        private readonly AnswerValidator $answerValidator,
        private readonly ProjectKnowledgeService $knowledge,
        private readonly AnswerFitnessScorer $fitness,
    ) {}

    public function confirm(ConsultationSession $session, User $user): ConsultationSession
    {
        if (in_array($session->status, [ConsultationSession::STATUS_QUEUED, ConsultationSession::STATUS_COMPLETED], true)) {
            return $session->refresh();
        }

        if ($session->status !== ConsultationSession::STATUS_REVIEW) {
            throw ValidationException::withMessages(['session' => 'راجع الإجابات قبل إرسالها للتحليل.']);
        }

        if ($session->conflicts()->where('status', 'open')->exists()) {
            abort(409, 'حلّ التعارضات المفتوحة قبل إرسال الاستشارة للتحليل.');
        }

        DB::transaction(function () use ($session): void {
            $locked = ConsultationSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $locked->answers()->update(['confirmed_at' => now()]);
            $locked->forceFill(['status' => ConsultationSession::STATUS_QUEUED, 'confirmed_at' => now(), 'last_activity_at' => now()])->save();
            $this->events->record($locked, 'analysis_queued', ['status' => ConsultationSession::STATUS_QUEUED]);
        });

        $result = $this->diagnosis->run($session->project, $user, FullDiagnosisRunner::MODE_AUTO, $session->id);
        if (($result['started_count'] ?? 0) === 0) {
            $session->forceFill([
                'status' => ConsultationSession::STATUS_FAILED,
                'scope_snapshot' => array_merge($session->scope_snapshot ?? [], ['analysis_error' => $result['message']]),
            ])->save();
            $this->events->record($session, 'analysis_failed', ['status' => ConsultationSession::STATUS_FAILED]);
        }

        return $session->refresh();
    }

    public function start(Project $project, User $user, string $depth = 'standard'): ConsultationSession
    {
        $depth = in_array($depth, ['quick', 'standard', 'deep'], true) ? $depth : 'standard';
        $existing = $project->consultationSessions()->whereIn('status', [
            ConsultationSession::STATUS_ACTIVE, ConsultationSession::STATUS_REVIEW,
            ConsultationSession::STATUS_QUEUED, ConsultationSession::STATUS_FAILED,
        ])->latest()->first();
        if ($existing !== null) {
            $this->events->record($existing, 'session_resumed', ['depth' => $existing->depth, 'status' => $existing->status]);

            return $existing->load(['currentQuestion.definition', 'moduleStates.module']);
        }

        $version = ConsultationBlueprint::where('key', 'smart-marketing-consultation')->firstOrFail()->currentVersion;

        return DB::transaction(function () use ($project, $user, $depth, $version): ConsultationSession {
            $session = ConsultationSession::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'blueprint_version_id' => $version->id,
                'status' => ConsultationSession::STATUS_ACTIVE,
                'depth' => $depth,
                'actual_stage' => $project->stage,
                'last_activity_at' => now(),
            ]);

            $version->load('modules.module');
            $known = ProjectAnswer::where('project_id', $project->id)->get()
                ->mapWithKeys(fn (ProjectAnswer $answer) => [$answer->field_key => data_get($answer->value_json, 'value')])->all();
            foreach ($version->modules as $binding) {
                $resolved = $this->scope->resolve($binding, $project, $known);
                $session->moduleStates()->create([
                    'diagnostic_module_id' => $binding->diagnostic_module_id,
                    ...$resolved,
                ]);
            }

            $this->advance($session);
            $this->events->record($session, 'session_started', ['depth' => $session->depth, 'status' => $session->status]);

            return $session->refresh()->load(['currentQuestion.definition', 'moduleStates.module']);
        });
    }

    /** @param array<string,mixed> $payload */
    public function answer(ConsultationSession $session, QuestionVersion $question, array $payload): ConsultationSession
    {
        return DB::transaction(function () use ($session, $question, $payload): ConsultationSession {
            $session = ConsultationSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== ConsultationSession::STATUS_ACTIVE || $session->current_question_version_id !== $question->id) {
                throw ValidationException::withMessages(['question' => 'هذا السؤال لم يعد السؤال الحالي. حدّث الصفحة للمتابعة.']);
            }

            $unknown = (bool) ($payload['unknown'] ?? false);
            $skipped = (bool) ($payload['skipped'] ?? false);
            $value = $payload['value'] ?? null;
            $this->answerValidator->validate($question, $payload);
            if (! $unknown && ! $skipped && ($value === null || $value === '' || $value === [])) {
                throw ValidationException::withMessages(['value' => 'اختر إجابة أو استخدم «لا أعرف».']);
            }

            $existingAnswer = ConsultationAnswer::where('consultation_session_id', $session->id)
                ->where('question_version_id', $question->id)->first();
            ConsultationAnswer::updateOrCreate(
                ['consultation_session_id' => $session->id, 'question_version_id' => $question->id],
                ['value_json' => ['value' => $value], 'source' => 'user', 'confidence' => $unknown ? 'low' : 'medium', 'is_unknown' => $unknown, 'is_skipped' => $skipped],
            );

            if ($unknown) {
                ConsultationInference::updateOrCreate(
                    ['consultation_session_id' => $session->id, 'key' => 'missing.'.$question->definition->internal_variable],
                    ['type' => 'missing_information', 'statement' => 'لا تتوفر معلومة مؤكدة عن '.$question->user_text, 'confidence' => 0, 'status' => 'open'],
                );
            } elseif (! $skipped) {
                $this->knowledge->record(
                    $session->project,
                    $question->definition->internal_variable,
                    $value,
                    'consultation',
                    $session->uuid,
                    $session->id,
                    $unknown ? 'low' : 'medium',
                );

                /*
                 * الكفاية تُقاس عند الإجابة لا عند التقرير: هنا وحدها تُعرف
                 * الإجابة ونوعها معًا، وهنا يمكن أن يُقال لصاحبها إن وصفه عامٌّ
                 * وهو ما زال أمام السؤال. تأجيلها إلى الحساب يعني أن يكتشف
                 * ضعف مدخلاته في تقرير لا يستطيع تعديله.
                 */
                $this->fitness->score(
                    $session->project,
                    $question->definition->internal_variable,
                    $value,
                    $question->answer_type,
                );
            }

            if ($question->definition->internal_variable === 'consultation_depth' && ! $unknown && ! $skipped) {
                $session->depth = match ($value) {
                    'سريع' => 'quick',
                    'متعمق' => 'deep',
                    default => 'standard',
                };
            }

            $session->forceFill(['questions_answered' => $session->answers()->count(), 'last_activity_at' => now()])->save();
            $this->events->record($session, $existingAnswer ? 'answer_revised' : 'answer_saved', [
                'question_key' => $question->definition->key,
                'answer_type' => $question->answer_type,
                'source' => 'web_or_api',
                'is_unknown' => $unknown,
                'is_skipped' => $skipped,
                'questions_answered' => $session->questions_answered,
            ]);
            $this->conflicts->refresh($session);
            $this->refreshScope($session);
            $this->advance($session);

            return $session->refresh()->load(['currentQuestion.definition', 'moduleStates.module']);
        });
    }

    /** @param array<string,mixed> $payload */
    public function revise(ConsultationSession $session, QuestionVersion $question, array $payload): ConsultationSession
    {
        return DB::transaction(function () use ($session, $question, $payload): ConsultationSession {
            $session = ConsultationSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($session->status, [ConsultationSession::STATUS_ACTIVE, ConsultationSession::STATUS_REVIEW], true)) {
                throw ValidationException::withMessages(['session' => 'لا يمكن تعديل الإجابات بعد بدء التحليل.']);
            }
            $answer = $session->answers()->where('question_version_id', $question->id)->firstOrFail();
            $this->answerValidator->validate($question, $payload);
            $unknown = (bool) ($payload['unknown'] ?? false);
            $skipped = (bool) ($payload['skipped'] ?? false);
            $value = $payload['value'] ?? null;
            $answer->forceFill([
                'value_json' => ['value' => $value], 'source' => 'user',
                'confidence' => $unknown ? 'low' : 'medium', 'is_unknown' => $unknown,
                'is_skipped' => $skipped, 'confirmed_at' => null,
            ])->save();
            $variable = $question->definition->internal_variable;
            if ($unknown || $skipped) {
                $this->knowledge->retract(
                    $session->project,
                    $variable,
                    'consultation',
                    $session->uuid,
                    $session->id,
                    ['reason' => $unknown ? 'unknown' : 'skipped'],
                );
                ConsultationInference::updateOrCreate(
                    ['consultation_session_id' => $session->id, 'key' => 'missing.'.$variable],
                    ['type' => 'missing_information', 'statement' => 'لا تتوفر معلومة مؤكدة عن '.$question->user_text, 'confidence' => 0, 'status' => 'open'],
                );

                /*
                 * سحب المعلومة يسحب درجة كفايتها: درجةٌ باقية على إجابة أُلغيت
                 * تُخفض محورًا بمدخل لم يعد موجودًا — وهو رقم لا يُعرف كيف حُسب.
                 */
                AnswerFitness::where('project_id', $session->project_id)
                    ->where('field_key', $variable)
                    ->delete();
            } else {
                $this->knowledge->record(
                    $session->project,
                    $variable,
                    $value,
                    'consultation',
                    $session->uuid,
                    $session->id,
                    'medium',
                );
                $this->fitness->score($session->project, $variable, $value, $question->answer_type);
                $session->inferences()->where('key', 'missing.'.$variable)->delete();
            }
            if ($variable === 'consultation_depth' && ! $unknown && ! $skipped) {
                $session->depth = match ($value) {
                    'سريع' => 'quick', 'متعمق' => 'deep', default => 'standard'
                };
            }
            $session->last_activity_at = now();
            $session->save();
            $this->events->record($session, 'answer_revised', ['question_key' => $question->definition->key, 'answer_type' => $question->answer_type, 'source' => 'web_or_api', 'is_unknown' => $unknown, 'is_skipped' => $skipped]);
            $this->conflicts->refresh($session);
            $this->refreshScope($session);

            return $session->refresh()->load(['currentQuestion.definition', 'moduleStates.module']);
        });
    }

    public function review(ConsultationSession $session): ConsultationSession
    {
        if ($session->status === ConsultationSession::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['session' => 'أكمل الأسئلة الحالية قبل المراجعة النهائية.']);
        }
        $this->conflicts->refresh($session);
        $this->events->record($session, 'review_opened', ['status' => $session->status]);

        return $session->refresh();
    }

    public function retry(ConsultationSession $session, User $user): ConsultationSession
    {
        if ($session->status !== ConsultationSession::STATUS_FAILED) {
            throw ValidationException::withMessages(['session' => 'إعادة المحاولة متاحة فقط بعد فشل التحليل.']);
        }
        $session->forceFill(['status' => ConsultationSession::STATUS_REVIEW, 'scope_snapshot' => array_diff_key($session->scope_snapshot ?? [], ['analysis_error' => true])])->save();

        return $this->confirm($session->refresh(), $user);
    }

    public function resolveConflict(ConsultationSession $session, ConsultationConflict $conflict, string $resolution): ConsultationSession
    {
        if ($conflict->consultation_session_id !== $session->id || $conflict->status !== 'open') {
            throw ValidationException::withMessages(['conflict' => 'هذا التعارض غير متاح للحل.']);
        }
        $conflict->forceFill([
            'status' => 'resolved',
            'resolution' => ['statement' => $resolution, 'source' => 'user'],
            'resolved_at' => now(),
        ])->save();
        $this->events->record($session, 'conflict_resolved', ['conflict_key' => $conflict->key]);

        return $session->refresh();
    }

    private function advance(ConsultationSession $session): void
    {
        $next = $this->questions->next($session->loadMissing(['blueprintVersion', 'project.profile']));
        $session->forceFill([
            'current_question_version_id' => $next?->id,
            'status' => $next === null ? ConsultationSession::STATUS_REVIEW : ConsultationSession::STATUS_ACTIVE,
        ])->save();
    }

    private function refreshScope(ConsultationSession $session): void
    {
        $known = ProjectAnswer::where('project_id', $session->project_id)->get()
            ->mapWithKeys(fn (ProjectAnswer $answer) => [$answer->field_key => data_get($answer->value_json, 'value')])->all();
        $session->loadMissing('blueprintVersion.modules.module', 'project.profile');
        foreach ($session->blueprintVersion->modules as $binding) {
            $session->moduleStates()->where('diagnostic_module_id', $binding->diagnostic_module_id)
                ->update($this->scope->resolve($binding, $session->project, $known));
        }
        $session->unsetRelation('moduleStates');
    }
}
