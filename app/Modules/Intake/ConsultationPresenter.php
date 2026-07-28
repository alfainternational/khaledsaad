<?php

namespace App\Modules\Intake;

use App\Models\ConsultationSession;
use App\Models\QuestionVersion;

class ConsultationPresenter
{
    /** @return array<string,mixed> */
    public function show(ConsultationSession $session): array
    {
        $session->loadMissing([
            'project', 'blueprintVersion', 'currentQuestion.definition', 'moduleStates.module',
            'answers.questionVersion.definition', 'conflicts', 'inferences', 'evidence', 'agencyReport',
        ]);
        $question = $session->currentQuestion;
        $limit = (int) data_get($session->blueprintVersion->settings, "depth_limits.{$session->depth}", 35);

        return [
            'uuid' => $session->uuid,
            'status' => $session->status,
            'depth' => $session->depth,
            'project' => ['slug' => $session->project->slug, 'name' => $session->project->name],
            'progress' => [
                'answered' => $session->questions_answered,
                'limit' => $limit,
                'percent' => min(95, (int) round($session->questions_answered / max(1, $limit) * 100)),
                'label' => $this->progressLabel($session),
            ],
            'question' => $question === null ? null : [
                'key' => $question->definition->key,
                'text' => $question->user_text,
                'help' => $question->help_text,
                'why' => $question->why_text,
                'type' => $question->answer_type,
                'options' => $this->options($question),
                'validation' => $question->validation ?? [],
                'required' => $question->required,
                'allow_unknown' => $question->allow_unknown,
                'allow_skip' => $question->allow_skip,
                'sensitive' => $question->definition->sensitivity === 'sensitive',
            ],
            'scope' => $session->moduleStates->map(fn ($state) => [
                'key' => $state->module->key,
                'name' => $state->module->name,
                'state' => $state->state,
                'reason' => $state->reason,
                'confidence' => $state->confidence,
            ])->values()->all(),
            'conflicts' => $session->conflicts->where('status', 'open')->map(fn ($conflict) => [
                'id' => $conflict->id,
                'key' => $conflict->key,
                'message' => $conflict->message,
                'severity' => $conflict->severity,
            ])->values()->all(),
            'review' => $this->review($session),
            'evidence' => $session->evidence->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->source_label,
                'type' => $item->type,
                'confidence' => $item->confidence,
                'size' => data_get($item->metadata, 'size'),
                'mime_type' => $item->mime_type,
                'extraction_status' => $item->extraction_status,
                'sha256' => $item->sha256,
                'review_required' => (bool) data_get($item->metadata, 'review_required', false),
            ])->values()->all(),
            'report_uuid' => $session->agencyReport?->uuid,
            'status_message' => $this->statusMessage($session),
            'can_confirm' => $session->status === ConsultationSession::STATUS_REVIEW
                && $session->conflicts->where('status', 'open')->isEmpty(),
        ];
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function review(ConsultationSession $session): array
    {
        $facts = [];
        $estimates = [];
        $unknowns = [];

        foreach ($session->answers as $answer) {
            $item = [
                'question_key' => $answer->questionVersion->definition->key,
                'label' => $answer->questionVersion->user_text,
                'value' => data_get($answer->value_json, 'value'),
                'source' => $answer->source,
                'confidence' => $answer->confidence,
                'type' => $answer->questionVersion->answer_type,
                'options' => $this->options($answer->questionVersion),
                'validation' => $answer->questionVersion->validation ?? [],
                'required' => $answer->questionVersion->required,
                'allow_unknown' => $answer->questionVersion->allow_unknown,
                'allow_skip' => $answer->questionVersion->allow_skip,
            ];
            if ($answer->is_unknown || $answer->is_skipped) {
                $unknowns[] = $item;
            } elseif ($answer->source === 'estimate' || $answer->confidence === 'low') {
                $estimates[] = $item;
            } else {
                $facts[] = $item;
            }
        }

        return [
            'facts' => $facts,
            'estimates' => $estimates,
            'unknowns' => $unknowns,
            'assumptions' => $session->inferences->whereIn('type', ['assumption', 'inference', 'missing_information'])
                ->map(fn ($item) => ['key' => $item->key, 'statement' => $item->statement, 'confidence' => $item->confidence])
                ->values()->all(),
            'conflicts' => $session->conflicts->where('status', 'open')
                ->map(fn ($item) => ['id' => $item->id, 'key' => $item->key, 'message' => $item->message, 'severity' => $item->severity])
                ->values()->all(),
        ];
    }

    private function statusMessage(ConsultationSession $session): string
    {
        return match ($session->status) {
            ConsultationSession::STATUS_ACTIVE => 'الاستشارة قيد الاستكمال.',
            ConsultationSession::STATUS_REVIEW => 'راجع المعلومات والتعارضات قبل التحليل.',
            ConsultationSession::STATUS_QUEUED => 'يجري الآن تحليل الإجابات وبناء التقرير الموحد.',
            ConsultationSession::STATUS_COMPLETED => 'اكتمل التقرير الموحد وأصبح جاهزًا.',
            ConsultationSession::STATUS_FAILED => (string) data_get($session->scope_snapshot, 'analysis_error', 'تعذر إكمال التحليل. يمكنك إعادة المحاولة.'),
            default => 'حالة الاستشارة غير معروفة.',
        };
    }

    /** @return array<int,array{value:mixed,label:string}> */
    private function options(QuestionVersion $question): array
    {
        if ($question->answer_type === 'boolean' && empty($question->options)) {
            return [['value' => '1', 'label' => 'نعم'], ['value' => '0', 'label' => 'لا']];
        }

        return $question->options ?? [];
    }

    private function progressLabel(ConsultationSession $session): string
    {
        if ($session->status === ConsultationSession::STATUS_REVIEW) {
            return 'اكتملت المعلومات الأساسية؛ راجع ما فهمناه.';
        }
        if ($session->questions_answered < 5) {
            return 'نفهم مشروعك ونحدد نطاق التشخيص.';
        }
        if ($session->questions_answered < 15) {
            return 'نحدد الأسباب والفرص الأكثر تأثيرًا.';
        }

        return 'بقيت أسئلة قليلة لحسم التشخيص.';
    }
}
