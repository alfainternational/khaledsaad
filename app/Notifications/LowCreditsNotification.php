<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * يُرسَل حين ينخفض رصيد المحفظة تحت الحد بعد آخر خصم.
 */
class LowCreditsNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly int $balance) {}

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
            ->subject(__('رصيدك أوشك على الانتهاء'))
            ->greeting(__('تنبيه رصيد'))
            ->line("رصيدك الحالي {$this->balance}، وقد لا يكفي لبدء تشخيص جديد.")
            ->action(__('راجع الخطط وخيارات الرصيد'), route('app.billing'))
            ->line(__('يمكنك الترقية أو شراء حزمة أرصدة في أي وقت.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'low_credits',
            'title' => __('رصيدك أوشك على الانتهاء'),
            'body' => "رصيدك الحالي {$this->balance}.",
            'url' => route('app.billing'),
        ];
    }
}
