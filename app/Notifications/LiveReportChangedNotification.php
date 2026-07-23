<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * يُرسَل حين يكتشف فحص التقرير الحي أن مدخلات التقرير تغيّرت فعلًا.
 * هذا هو وجه «الرؤى المستمرة»: المنصة تعود للمستخدم، لا العكس.
 */
class LiveReportChangedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{type: string, text: string}>  $changes
     */
    public function __construct(
        public readonly Report $report,
        public readonly array $changes,
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
            ->subject('تغيّر شيء في مشروعك — '.$this->report->title)
            ->greeting('تقريرك الحي رصد تغييرًا')
            ->line("منذ إصدار «{$this->report->title}» تغيّر ما بُني عليه:");

        foreach (array_slice($this->changes, 0, 4) as $change) {
            $mail->line('• '.$change['text']);
        }

        return $mail
            ->action('افتح التقرير وقرر', route('app.reports.show', $this->report->id))
            ->line('إجاباتك محفوظة — إعادة التحليل ببياناتك الجديدة تأخذ دقائق.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'live_report_changed',
            'title' => 'مشروعك تغيّر بعد آخر تحليل',
            'body' => $this->changes[0]['text']
                .(count($this->changes) > 1 ? ' و'.(count($this->changes) - 1).' تغييرات أخرى.' : ''),
            'report_id' => $this->report->id,
            'url' => route('app.reports.show', $this->report->id),
        ];
    }
}
