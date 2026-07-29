<?php

namespace App\Modules\Reporting;

use App\Models\AgencyReport;
use App\Models\AgencyReportView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

    public function __construct(private readonly AgencyReportDocumentAdapter $documents) {}

    public function share(AgencyReport $report, int $days = self::DEFAULT_DAYS): AgencyReport
    {
        $this->assertReady($report);
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
        $snapshot = $this->agencySnapshot($report);

        return [
            'document' => [
                'title' => 'موجز التكليف — '.($report->snapshot['agency_brief']['project']['name'] ?? $report->project->name),
                'version' => $report->version,
                'generated_at' => $report->generated_at?->toIso8601String(),
            ],
            'snapshot' => $snapshot,
        ];
    }

    /**
     * قائمة سماح لمحتوى الوكالة. ما لا يدخل هنا لا يمكن أن يتسرّب عبر
     * الويب أو التطبيق أو الملف الآلي حتى لو أضيف لاحقًا إلى لقطة المالك.
     *
     * @return array{agency_brief: array<string, mixed>}
     */
    public function agencySnapshot(AgencyReport $report): array
    {
        if (! isset($report->snapshot['agency_brief']) && $this->isLive($report)) {
            return $this->documents->legacyAgencySnapshot($report);
        }

        $this->assertReady($report);

        return ['agency_brief' => $report->snapshot['agency_brief']];
    }

    private function assertReady(AgencyReport $report): void
    {
        $readiness = $report->snapshot['agency_brief']['readiness'] ?? null;

        if (($readiness['is_ready'] ?? false) === true) {
            return;
        }

        throw ValidationException::withMessages([
            'brief' => $readiness['message'] ?? 'موجز الوكالة غير مكتمل. أنشئ إصدارًا جديدًا بعد حسم البنود المطلوبة.',
        ]);
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
