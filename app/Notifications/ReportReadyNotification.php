<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * يُرسَل حين يكتمل تحليل ويصبح التقرير جاهزًا.
 * قناتان: قاعدة البيانات (جرس داخل المنصة) والبريد.
 */
class ReportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Report $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('app.reports.show', $this->report->id);

        return (new MailMessage)
            ->subject('تقريرك جاهز — '.$this->report->title)
            ->greeting('تحليلك اكتمل')
            ->line("انتهى تحليل «{$this->report->title}».")
            ->line("درجتك: {$this->report->score} من 100.")
            ->action('افتح التقرير', $url)
            ->line('إجاباتك ومهامك محفوظة، ويمكنك تحويل التوصيات إلى مهام في أي وقت.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'report_ready',
            'title' => 'تقريرك جاهز',
            'body' => "انتهى تحليل «{$this->report->title}» بدرجة {$this->report->score}.",
            'report_id' => $this->report->id,
            'url' => route('app.reports.show', $this->report->id),
        ];
    }
}
