<?php

namespace App\Jobs;

use App\Models\ConsultationSession;
use App\Models\Project;
use App\Models\User;
use App\Services\Consultations\ConsultationEventRecorder;
use App\Services\Reports\AgencyReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ما يجعل التشخيص الشامل «أمرًا واحدًا» فعلًا: بعد انتهاء آخر أداة يُبنى
 * المستند الموحّد تلقائيًا دون أن يعود صاحب المشروع ليضغط زرًا ثانيًا.
 *
 * لا يُسقط شيئًا إن تعذّر: نقص الأدوات الأساسية يعني أن المستند لم يحن وقته
 * بعد، وهذا وضع مشروع لا خطأ يستحق إيقاف الطابور.
 */
class FinishFullDiagnosis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $projectId,
        public readonly int $userId,
        public readonly ?int $consultationSessionId = null,
    ) {}

    public function handle(AgencyReportService $reports, ?ConsultationEventRecorder $events = null): void
    {
        $events ??= app(ConsultationEventRecorder::class);
        $project = Project::find($this->projectId);
        $user = User::find($this->userId);

        if ($project === null || $user === null) {
            return;
        }

        if (! $reports->readiness($project)['can_generate']) {
            Log::info('التشخيص الشامل انتهى دون اكتمال الأدوات الأساسية', [
                'project' => $project->id,
            ]);

            $this->failConsultation('لم تكتمل أدوات التشخيص الأساسية لبناء التقرير.', $events);

            return;
        }

        try {
            $session = $this->consultationSessionId === null
                ? null
                : ConsultationSession::find($this->consultationSessionId);
            $report = $reports->generate($project, $user, [], $session);
            if ($this->consultationSessionId !== null) {
                if ($session !== null) {
                    $session->forceFill(['status' => ConsultationSession::STATUS_COMPLETED, 'completed_at' => now()])->save();
                    $events->record($session, 'analysis_completed', ['status' => ConsultationSession::STATUS_COMPLETED, 'report_uuid' => $report->uuid]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('تعذر بناء المستند الموحّد بعد التشخيص الشامل', [
                'project' => $project->id,
                'error' => $exception->getMessage(),
            ]);
            $this->failConsultation('تعذر بناء التقرير الموحد. أعد المحاولة أو تواصل مع الدعم.', $events);
        }
    }

    private function failConsultation(string $message, ConsultationEventRecorder $events): void
    {
        if ($this->consultationSessionId === null) {
            return;
        }
        $session = ConsultationSession::find($this->consultationSessionId);
        if ($session === null) {
            return;
        }
        $session->forceFill([
            'status' => ConsultationSession::STATUS_FAILED,
            'scope_snapshot' => array_merge($session->scope_snapshot ?? [], ['analysis_error' => $message]),
        ])->save();
        $events->record($session, 'analysis_failed', ['status' => ConsultationSession::STATUS_FAILED]);
    }
}
