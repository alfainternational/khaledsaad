<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * يُرسَل حين تتأخر مهمة عن موعدها ولم تُنجَز.
 * يُطلق من أمر مجدول، مرة واحدة لكل مهمة (يُعلَّم عليها بعد الإرسال).
 */
class TaskOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Task $task) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('مهمة تأخّرت — '.$this->task->title)
            ->greeting('مهمة تنتظرك')
            ->line("تأخرت مهمة «{$this->task->title}» عن موعدها.")
            ->action('افتح المهام', route('app.projects.tasks', $this->task->project->slug))
            ->line('حدّث حالة المهمة أو اختر لها موعدًا جديدًا.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_overdue',
            'title' => 'مهمة تأخّرت',
            'body' => "«{$this->task->title}» تجاوزت موعدها.",
            'task_id' => $this->task->id,
            'url' => route('app.projects.tasks', $this->task->project->slug),
        ];
    }
}
