<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * رمز الدخول لخطوة التحقق الثانية (بند ٢٣) — بريد فقط، لا يُخزَّن في قاعدة
 * البيانات: الرمز سر قصير العمر ولا معنى لأرشفته.
 */
class LoginOtpNotification extends Notification
{
    public function __construct(private readonly string $code) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('رمز دخولك — يصلح لعشر دقائق'))
            ->greeting('مرحبًا '.$notifiable->name)
            ->line(__('طُلب الدخول إلى حسابك، وهذه خطوة التحقق التي فعّلتها بنفسك.'))
            ->line('**رمز الدخول: '.$this->code.'**')
            ->line(__('الرمز يصلح لعشر دقائق ولمحاولة دخول واحدة. إن لم تكن أنت من يحاول الدخول، غيّر كلمة مرورك الآن.'));
    }
}
