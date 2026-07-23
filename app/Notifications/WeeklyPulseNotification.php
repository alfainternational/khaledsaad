<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * نبض الأسبوع: رسالة واحدة كل أسبوع تلخّص ما تغيّر وما الخطوة —
 * إيقاع المنصة الدوري الذي يعيد المستخدم دون أن يطلب شيئًا.
 */
class WeeklyPulseNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $highlights  أبرز عناوين النبض عبر المشاريع.
     */
    public function __construct(
        public readonly int $projectCount,
        public readonly array $highlights,
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
        $mail = (new MailMessage)
            ->subject('نبض الأسبوع — ماذا تغيّر في مشاريعك')
            ->greeting('خلاصة أسبوعك جاهزة');

        foreach (array_slice($this->highlights, 0, 3) as $highlight) {
            $mail->line('• '.$highlight);
        }

        return $mail
            ->action('افتح النبض كاملًا', route('app.pulse.index'))
            ->line('دقيقتان تكفيان لتعرف أين تقف وماذا تفعل هذا الأسبوع.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'weekly_pulse',
            'title' => 'نبض الأسبوع جاهز',
            'body' => $this->highlights[0] ?? 'خلاصة أسبوعك عبر '.$this->projectCount.' مشاريع.',
            'url' => route('app.pulse.index'),
        ];
    }
}
