<?php

namespace App\Notifications;

use App\Models\ToolRun;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * يُرسَل حين يفشل التحليل تقنيًا.
 * الرسالة تطمئن قبل أن تعتذر: الإجابات محفوظة والرصيد مُسترد.
 */
class AnalysisFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ToolRun $run) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tool = $this->run->toolVersion->tool->title;

        return (new MailMessage)
            ->subject('تعذّر إكمال التحليل — نعيد المحاولة')
            ->greeting('حدث تعثّر تقني')
            ->line("لم يكتمل تحليل «{$tool}» هذه المرة.")
            ->line('إجاباتك محفوظة بالكامل، ورصيدك أُعيد إليك — لم تخسر شيئًا.')
            ->action('أعد المحاولة', route('app.runs.status', $this->run->uuid))
            ->line('إعادة الطلب لا تكلفك إدخالًا جديدًا.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'analysis_failed',
            'title' => 'تعذّر إكمال التحليل',
            'body' => 'إجاباتك محفوظة ورصيدك مُسترد. يمكنك إعادة المحاولة.',
            'run_uuid' => $this->run->uuid,
            'url' => route('app.runs.status', $this->run->uuid),
        ];
    }
}
