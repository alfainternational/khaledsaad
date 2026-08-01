<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تذكير قبل الموعد لا بعده.
 *
 * `TaskOverdueNotification` يصل بعد فوات الأوان: يخبر صاحب النشاط أنه
 * تأخّر، وهو يعرف. التذكير المبكر هو ما ينقذ المهمة فعلًا، ولذلك يحمل
 * أول خطوة نصًّا — لا رابطًا يفتحه ليعرف ماذا كان ينوي.
 */
class TaskReminderNotification extends Notification
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
        $message = (new MailMessage)
            ->subject('تذكير بمهمة — '.$this->task->title)
            ->greeting('مهمة تقترب من موعدها')
            ->line("مهمة «{$this->task->title}» موعدها {$this->dueLabel()}.");

        $firstStep = $this->firstStep();

        if ($firstStep !== null) {
            $message->line('ابدأ بهذه الخطوة: '.$firstStep);
        }

        return $message
            ->action('افتح المهام', route('app.projects.tasks', $this->task->project->slug))
            ->line('إن لم يعد الوقت مناسبًا، غيّر الموعد بدل أن تتركها تتأخر.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'task_reminder',
            'title' => 'تذكير بمهمة',
            'body' => $this->firstStep()
                ?? "«{$this->task->title}» موعدها {$this->dueLabel()}.",
            'task_id' => $this->task->id,
            'url' => route('app.projects.tasks', $this->task->project->slug),
        ];
    }

    private function firstStep(): ?string
    {
        $steps = $this->task->steps ?? [];

        return isset($steps[0]) && is_string($steps[0]) ? $steps[0] : null;
    }

    private function dueLabel(): string
    {
        return $this->task->due_date?->translatedFormat('j F') ?? 'قريب';
    }
}
