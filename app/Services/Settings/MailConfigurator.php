<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;

/**
 * يطبّق إعدادات البريد المخزّنة في قاعدة البيانات على إعداد Laravel وقت التشغيل.
 *
 * الغرض: يضبط الآدمن SMTP من اللوحة (مضيف، منفذ، مستخدم، كلمة مرور، مُرسِل)
 * دون لمس .env. إن لم تُضبط، يبقى الإعداد الافتراضي (log) فلا ينكسر شيء.
 */
class MailConfigurator
{
    public function apply(): void
    {
        $host = Setting::get('mail_host');

        // لا إعداد بريد بعد: نُبقي ما في .env (log افتراضيًا) دون تدخّل.
        if (blank($host)) {
            return;
        }

        Config::set('mail.default', Setting::get('mail_mailer', 'smtp'));
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) Setting::get('mail_port', 587));
        Config::set('mail.mailers.smtp.username', Setting::get('mail_username'));
        Config::set('mail.mailers.smtp.password', Setting::get('mail_password'));
        Config::set('mail.mailers.smtp.encryption', Setting::get('mail_encryption', 'tls') ?: null);

        $fromAddress = Setting::get('mail_from_address');

        if (filled($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', Setting::get('mail_from_name', config('app.name')));
        }
    }
}
