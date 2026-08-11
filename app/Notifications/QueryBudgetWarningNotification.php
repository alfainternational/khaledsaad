<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه بلوغ ٨٠٪ من سقف الاستعلامات الشهري (§٤.٤).
 *
 * السقف يوجب أمرين: تنبيهًا عند ٨٠٪ وتوقفًا عند ١٠٠٪. التوقف وحده يجعل
 * صاحب المساحة يكتشف الحدّ حين يصطدم به وسط عمل — والتنبيه هو ما يمنحه
 * فرصة أن يقرر قبل أن يُمنع.
 *
 * يُرسَل مرة واحدة لكل شهر: `warned_at` يمنع تكراره مع كل حجز تالٍ، فتنبيهٌ
 * يتكرر عشر مرات يُتجاهَل كضجيج ثم يُتجاهَل معه التوقف نفسه.
 */
class QueryBudgetWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $committed,
        public readonly int $limit,
        public readonly string $period,
    ) {}

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
            ->subject(__('اقتربت من سقف استعلامات هذا الشهر'))
            ->greeting(__('تنبيه ميزانية'))
            ->line($this->body())
            ->line(__('عند بلوغ السقف يتوقف الاستطلاع والنسخ الصوتي تلقائيًّا حتى بداية الشهر القادم.'))
            ->action(__('راجع خطتك'), route('app.billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'query_budget_warning',
            'title' => __('اقتربت من سقف استعلامات هذا الشهر'),
            'body' => $this->body(),
            'url' => route('app.billing'),
        ];
    }

    private function body(): string
    {
        // الرقم مع أساسه دائمًا (§١٣): «٨٠٪» وحدها لا تقول كم بقي.
        return "استُهلك {$this->committed} من {$this->limit} استعلامًا في {$this->period}.";
    }
}
