<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه تشغيلي: مزوّد الذكاء يقترب من العجز أو بلغه.
 *
 * يذهب إلى المشغّل لا إلى المستخدم. الفرق جوهري: المستخدم لا يملك حيال
 * هذا شيئًا (INV-8)، والمشغّل يملك كل شيء — ولا يعرف إلا إن أُخبر.
 */
class ProviderCapacityAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $alerts
     */
    public function __construct(public readonly array $alerts) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('تنبيه: قدرة مزوّد الذكاء'))
            ->line(__('رُصدت الحالات التالية قبل أن تصل إلى أي مستخدم:'));

        foreach ($this->alerts as $alert) {
            $mail->line('• '.$alert);
        }

        return $mail->line(__('التشغيلات المتأثرة محفوظة وتُعاد تلقائيًّا عند عودة القدرة.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('تنبيه: قدرة مزوّد الذكاء'),
            'body' => implode(' · ', $this->alerts),
            'url' => route('admin.operations'),
        ];
    }
}
