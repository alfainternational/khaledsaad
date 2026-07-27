<?php

namespace App\Services\Reports;

use App\Models\AgencyReport;
use App\Models\AgencyReportView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * رابط مشاركة موجز الوكالة.
 *
 * القواعد التي يقوم عليها:
 * - الرمز عشوائي طويل، والوصول به وحده — لا فهرسة ولا تخمين.
 * - لكل رابط تاريخ انتهاء إلزامي؛ لا رابط أبدي بالخطأ.
 * - الإلغاء فوري ولا يمكن التراجع عنه: إعادة المشاركة تُنشئ رمزًا جديدًا.
 * - كل فتحة تُسجَّل ببصمة مجزّأة لا بعنوان صريح.
 */
class AgencyReportSharing
{
    public const EXPIRY_CHOICES = [7, 30, 90];

    private const DEFAULT_DAYS = 30;

    public function share(AgencyReport $report, int $days = self::DEFAULT_DAYS): AgencyReport
    {
        $days = in_array($days, self::EXPIRY_CHOICES, true) ? $days : self::DEFAULT_DAYS;

        $report->forceFill([
            'share_token' => Str::random(48),
            'share_created_at' => now(),
            'share_expires_at' => now()->addDays($days),
            'share_revoked_at' => null,
        ])->save();

        return $report;
    }

    public function revoke(AgencyReport $report): AgencyReport
    {
        // الرمز يُمحى لا يُعطَّل فقط: رابط ملغى لا يجوز أن يعود بأي تعديل لاحق.
        $report->forceFill([
            'share_token' => null,
            'share_revoked_at' => now(),
        ])->save();

        return $report;
    }

    public function resolve(string $token): ?AgencyReport
    {
        $report = AgencyReport::where('share_token', $token)->first();

        if ($report === null || ! $this->isLive($report)) {
            return null;
        }

        return $report;
    }

    public function isLive(AgencyReport $report): bool
    {
        return $report->share_token !== null
            && $report->share_revoked_at === null
            && $report->share_expires_at !== null
            && $report->share_expires_at->isFuture();
    }

    public function record(AgencyReport $report, Request $request, string $channel = 'web'): void
    {
        AgencyReportView::create([
            'agency_report_id' => $report->id,
            'channel' => $channel,
            // بصمة مجزّأة بمفتاح التطبيق: تميّز الزائر المتكرر دون حفظ عنوانه.
            'viewer_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'viewed_at' => now(),
        ]);
    }

    /**
     * نسخة البيانات المرافقة للوكالة.
     *
     * فريق التنفيذ ينقل هذه الأرقام إلى لوحة القياس؛ نسخها يدويًا من PDF
     * مصدر أخطاء. وتُحذف منها نسخة صاحب المشروع الخاصة حتمًا لا اعتمادًا
     * على انتباه القالب.
     *
     * @return array<string, mixed>
     */
    public function dataFile(AgencyReport $report): array
    {
        $snapshot = $report->snapshot;
        unset($snapshot['owner_guide']);

        return [
            'document' => [
                'title' => $report->title,
                'version' => $report->version,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'source_report_ids' => $report->source_report_ids,
                'visibility' => $report->visibility,
            ],
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(AgencyReport $report): array
    {
        $views = $report->views()->orderByDesc('viewed_at')->get();

        return [
            'is_live' => $this->isLive($report),
            'url' => $this->isLive($report)
                ? route('shared.agency-report', $report->share_token)
                : null,
            'expires_at' => $report->share_expires_at?->toIso8601String(),
            'revoked_at' => $report->share_revoked_at?->toIso8601String(),
            'views_count' => $views->count(),
            'unique_viewers' => $views->pluck('viewer_hash')->filter()->unique()->count(),
            'last_viewed_at' => $views->first()?->viewed_at?->toIso8601String(),
            'expiry_choices' => self::EXPIRY_CHOICES,
        ];
    }
}
