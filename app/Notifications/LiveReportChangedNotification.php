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
            ->subject('بيانات مشروعك تغيّرت — '.$this->report->title)
            ->greeting(__('يوجد تحديث قد يؤثر في تقريرك'))
            ->line("تغيّرت بعض البيانات التي بُني عليها «{$this->report->title}»:");

        foreach (array_slice($this->changes, 0, 4) as $change) {
            $mail->line('• '.$change['text']);
        }

        return $mail
            ->action(__('راجع التقرير والتغييرات'), route('app.reports.show', $this->report->id))
            ->line(__('إجاباتك محفوظة، ويمكنك طلب تحليل جديد بعد مراجعة التغييرات.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'live_report_changed',
            'title' => __('مشروعك تغيّر بعد آخر تحليل'),
            'body' => $this->changes[0]['text']
                .(count($this->changes) > 1 ? ' و'.(count($this->changes) - 1).' تغييرات أخرى.' : ''),
            'report_id' => $this->report->id,
            'url' => route('app.reports.show', $this->report->id),
        ];
    }
}
