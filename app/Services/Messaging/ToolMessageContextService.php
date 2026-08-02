<?php

namespace App\Services\Messaging;

use App\Models\Report;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use App\Support\Messaging\PersonaName;

/**
 * يستخرج من التقرير سياقًا محدودًا وموثقًا لكتابة الرسائل.
 *
 * قاعدتان تحكمان هذا الصنف:
 *
 * ١) لا يُمرَّر التقرير كاملًا إلى النموذج. تقرير كامل داخل برومبت رسالة
 *    يُغرق النص بتفاصيل لا تخصّ القارئ، ويكلّف رموزًا بلا مقابل.
 *
 * ٢) لا يُنقل افتراض كحقيقة. الاستنتاجات (`is_assumption`) تُستبعد كلها،
 *    فما يصل النموذج هو ما له دليل في التقرير وحده — وإلا كتبنا إعلانًا
 *    يعد العميل بما لم يثبت.
 *
 * الأدوات المؤهلة خمس لا أكثر: أداة لا تنتج عرضًا ولا دليلًا ولا اعتراضًا
 * لا تعطي مادة رسالة، ونقطة دخول منها تَعِد بما لا تملك.
 */
class ToolMessageContextService
{
    /**
     * الأداة ← القناة والهدف المقترحان.
     *
     * مقترحان لا مفروضان: المستخدم يبدّلهما في الاستوديو.
     */
    private const QUALIFIED = [
        'brand-clarity' => [MessageChannel::Ad, MessageObjective::Attention],
        'audience-map' => [MessageChannel::Social, MessageObjective::Attention],
        'offer-builder' => [MessageChannel::Landing, MessageObjective::Action],
        'content-engine' => [MessageChannel::Social, MessageObjective::Attention],
        'campaign-planner' => [MessageChannel::Ad, MessageObjective::Action],
    ];

    public function qualifies(Report $report): bool
    {
        return isset(self::QUALIFIED[$this->toolKey($report)]);
    }

    /**
     * @return array<int, string>
     */
    public static function qualifiedTools(): array
    {
        return array_keys(self::QUALIFIED);
    }

    public function channelFor(Report $report): MessageChannel
    {
        return self::QUALIFIED[$this->toolKey($report)][0] ?? MessageChannel::Ad;
    }

    public function objectiveFor(Report $report): MessageObjective
    {
        return self::QUALIFIED[$this->toolKey($report)][1] ?? MessageObjective::Attention;
    }

    /**
     * السياق المحدود: عرض، أدلة، اعتراضات — كلها من مصدر مذكور.
     *
     * @return array<string, mixed>|null null إن لم تكن الأداة مؤهلة أو لم
     *                                   يبقَ بعد استبعاد الافتراضات شيء
     */
    public function contextFor(Report $report): ?array
    {
        if (! $this->qualifies($report)) {
            return null;
        }

        $report->loadMissing(['findings', 'project.profile', 'toolRun.toolVersion.tool']);

        // الافتراض يُستبعد كله: ما لا دليل عليه لا يدخل نص إعلان.
        $evidenced = $report->findings
            ->filter(fn ($finding) => ! $finding->is_assumption && filled($finding->evidence))
            ->take(4);

        $context = array_filter([
            'offer' => $report->project->profile?->value_proposition,
            'evidence' => $evidenced->map(fn ($finding) => [
                'claim' => $finding->title,
                'basis' => $finding->evidence,
            ])->values()->all(),
            'objections' => $report->findings
                ->filter(fn ($finding) => $finding->severity === 'high')
                ->take(3)->pluck('title')->values()->all(),
        ], fn ($value) => filled($value));

        // بلا عرض ولا دليل ولا اعتراض لا سياق — والفراغ يُعلن ولا يُملأ.
        return $context === [] ? null : $context;
    }

    /**
     * معاينة قصيرة داخل التقرير: ما ستعالجه رسالة كل شخصية.
     *
     * تُبنى بلا استدعاء نموذج — المعاينة ليست اقتراحًا، والاقتراح لا يُنتَج
     * إلا حين يطلبه المستخدم صراحةً داخل الاستوديو.
     *
     * @param  array<int, array<string, mixed>>  $personas
     * @return array<int, array<string, string>>
     */
    public function preview(array $personas, ?array $context): array
    {
        $offer = $context['offer'] ?? null;

        return array_map(fn (array $persona) => [
            'name' => PersonaName::display($persona['name'] ?? null),
            'angle' => $this->angle($persona, $offer),
        ], array_slice($personas, 0, 4));
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private function angle(array $persona, ?string $offer): string
    {
        $objection = $persona['objection'] ?? null;

        if (filled($objection)) {
            return "ستعالج اعتراضها: «{$objection}»";
        }

        return filled($offer)
            ? 'ستربط عرضك بما يهمّها تحديدًا.'
            : 'ستُبنى على دافعها ونبرتها.';
    }

    private function toolKey(Report $report): ?string
    {
        return $report->toolRun?->toolVersion?->tool?->key;
    }
}
