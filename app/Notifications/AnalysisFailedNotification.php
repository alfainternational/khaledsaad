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
            ->subject('لم يكتمل التحليل — إجاباتك محفوظة')
            ->greeting('لم نتمكن من إكمال التحليل هذه المرة')
            ->line("لم يكتمل تحليل «{$tool}» هذه المرة.")
            ->line('إجاباتك محفوظة، وأُعيد الرصيد المستخدم إلى حسابك.')
            ->action('حاول إكمال التحليل', route('app.runs.status', $this->run->uuid))
            ->line('لن تحتاج إلى إدخال إجاباتك مرة أخرى.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'analysis_failed',
            'title' => 'تعذّر إكمال التحليل',
            'body' => 'إجاباتك محفوظة، وأُعيد الرصيد المستخدم. يمكنك المحاولة مرة أخرى.',
            'run_uuid' => $this->run->uuid,
            'url' => route('app.runs.status', $this->run->uuid),
        ];
    }
}
