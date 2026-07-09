<?php

namespace App\Domain\AI\Kernel\Agents;

use App\Domain\AI\Kernel\Agents\Specialists\CustomerJourneySpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\EmailSequenceSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\GrowthLoopSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\InfluencerSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\LeadQualitySpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\LocalizationSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\MeasurementPlanSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\OfferConversionSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\PaidCampaignSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\PrOutreachSpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\SearchVisibilitySpecialist;
use App\Domain\AI\Kernel\Agents\Specialists\SocialContentSpecialist;

/**
 * محرّك «مراجعة الأخصائيين» — نقطة الدخول الموحّدة لكل الأخصائيين المحليين.
 *
 * المتصل يختار الجوانب المناسبة للسياق (المرحلة/الأداة/القالب)، والمحرّك يستدعي
 * كل أخصائي محلياً (بلا مورد خارجي) ويعيد درجة كلّية + لوحة لكل جانب جاهزة للعرض.
 * القدرات المبنية (hidden) تصبح قابلة للاستدعاء هنا خلف الصلاحيات، تجسيداً لمبدأ
 * «ابنِ شاملاً، اكشف انتقائياً». يتدهور بأمان: نص فارغ ⇒ لوحات فارغة.
 */
class SpecialistReviewService
{
    public const ASPECT_LOCALIZATION = 'localization';

    public const ASPECT_OFFER = 'offer';

    public const ASPECT_SEARCH = 'search';

    public const ASPECT_EMAIL = 'email';

    public const ASPECT_SOCIAL = 'social';

    public const ASPECT_CAMPAIGN = 'campaign';

    public const ASPECT_JOURNEY = 'journey';

    public const ASPECT_GROWTH = 'growth';

    public const ASPECT_PR = 'pr';

    public const ASPECT_INFLUENCER = 'influencer';

    public const ASPECT_MEASUREMENT = 'measurement';

    public const ASPECT_LEAD = 'lead';

    /** @var array<string, string> اسم بشري لكل جانب. */
    private const NAMES = [
        self::ASPECT_LOCALIZATION => 'صياغة عربية',
        self::ASPECT_OFFER => 'قوة العرض',
        self::ASPECT_SEARCH => 'الظهور في البحث',
        self::ASPECT_EMAIL => 'الإيميل والمتابعة',
        self::ASPECT_SOCIAL => 'السوشيال',
        self::ASPECT_CAMPAIGN => 'الإعلان المدفوع',
        self::ASPECT_JOURNEY => 'رحلة العميل',
        self::ASPECT_GROWTH => 'حلقة النمو',
        self::ASPECT_PR => 'العلاقات العامة',
        self::ASPECT_INFLUENCER => 'المؤثّرون',
        self::ASPECT_MEASUREMENT => 'القياس المتقدّم',
        self::ASPECT_LEAD => 'جودة الليدات',
    ];

    public function __construct(
        private readonly LocalizationSpecialist $localization,
        private readonly OfferConversionSpecialist $offer,
        private readonly SearchVisibilitySpecialist $search,
        private readonly EmailSequenceSpecialist $email,
        private readonly SocialContentSpecialist $social,
        private readonly PaidCampaignSpecialist $campaign,
        private readonly CustomerJourneySpecialist $journey,
        private readonly GrowthLoopSpecialist $growth,
        private readonly PrOutreachSpecialist $pr,
        private readonly InfluencerSpecialist $influencer,
        private readonly MeasurementPlanSpecialist $measurement,
        private readonly LeadQualitySpecialist $lead,
    ) {}

    /**
     * @param  array<int, string>  $aspects  مجموعة من ثوابت ASPECT_*
     * @param  array{title?: string, keyword?: string, subject?: string, platform?: string}  $meta
     * @return array{score: int|null, panels: array<int, array{key: string, name: string, score: int, items: array<int, string>}>}
     */
    public function review(string $text, array $aspects, array $meta = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['score' => null, 'panels' => []];
        }

        $panels = [];
        foreach ($aspects as $aspect) {
            $result = $this->runAspect($aspect, $text, $meta);
            if ($result === null) {
                continue;
            }
            $panels[] = [
                'key' => $aspect,
                'name' => self::NAMES[$aspect] ?? $aspect,
                'score' => (int) $result['score'],
                'items' => $this->itemsFrom($result),
            ];
        }

        return [
            'score' => $this->overall($panels),
            'panels' => $panels,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runAspect(string $aspect, string $text, array $meta): ?array
    {
        return match ($aspect) {
            self::ASPECT_LOCALIZATION => $this->localization->analyze($text),
            self::ASPECT_OFFER => $this->offer->analyze($text),
            self::ASPECT_SEARCH => $this->search->analyze((string) ($meta['title'] ?? ''), $text, (string) ($meta['keyword'] ?? '')),
            self::ASPECT_EMAIL => $this->email->analyze((string) ($meta['subject'] ?? ''), $text),
            self::ASPECT_SOCIAL => $this->social->analyze($text, (string) ($meta['platform'] ?? 'general')),
            self::ASPECT_CAMPAIGN => $this->campaign->analyze($text),
            self::ASPECT_JOURNEY => $this->journey->analyze($text),
            self::ASPECT_GROWTH => $this->growth->analyze($text),
            self::ASPECT_PR => $this->pr->analyze($text),
            self::ASPECT_INFLUENCER => $this->influencer->analyze($text),
            self::ASPECT_MEASUREMENT => $this->measurement->analyze($text),
            self::ASPECT_LEAD => $this->lead->analyze($text),
            default => null,
        };
    }

    /**
     * استخراج موحّد لعناصر اللوحة من نتيجة أي أخصائي.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, string>
     */
    private function itemsFrom(array $result): array
    {
        // أخصائي الصياغة يعيد issues بحقل label.
        if (isset($result['issues']) && is_array($result['issues'])) {
            return array_values(array_filter(array_map(
                fn ($i): string => (string) ($i['label'] ?? ''),
                $result['issues'],
            )));
        }

        // بقية الأخصائيين يعيدون findings بحقل hint (أو label احتياطياً).
        if (isset($result['findings']) && is_array($result['findings'])) {
            $items = array_values(array_filter(array_map(
                fn ($f): string => (string) ($f['hint'] ?? $f['label'] ?? ''),
                $result['findings'],
            )));

            // عرض بلا ملاحظات ⇒ اعرض نقاط القوة إن وُجدت (أخصائي العرض).
            if ($items === [] && ! empty($result['strengths'])) {
                return array_values(array_map('strval', (array) $result['strengths']));
            }

            return $items;
        }

        return [];
    }

    /**
     * @param  array<int, array{score: int}>  $panels
     */
    private function overall(array $panels): ?int
    {
        if ($panels === []) {
            return null;
        }

        $sum = array_sum(array_map(fn (array $p): int => (int) $p['score'], $panels));

        return (int) round($sum / count($panels));
    }
}
