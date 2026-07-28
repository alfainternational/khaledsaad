<?php

namespace App\Modules\Diagnosis;

use App\Modules\Shared\Evidence\EvidenceLevel;

/**
 * محور تشخيص واحد.
 *
 * ثمانية محاور، محرك واحد. ما يختلف بينها هو مصدر الدليل وعمقه لا طريقة
 * الحساب — ولذلك تُعرَّف بيانًا لا كودًا، فتبقى الدرجة قابلة لإعادة الإنتاج
 * والمراجعة.
 */
enum Axis: string
{
    case StrategicClarity = 'strategic_clarity';
    case AudienceUnderstanding = 'audience_understanding';
    case PositioningMessage = 'positioning_message';
    case ChannelStructure = 'channel_structure';
    case MeasurementData = 'measurement_data';
    case ExecutionCapacity = 'execution_capacity';
    case AiReadiness = 'ai_readiness';
    case OwnedAssets = 'owned_assets';

    public function label(): string
    {
        return match ($this) {
            self::StrategicClarity => 'الوضوح الاستراتيجي',
            self::AudienceUnderstanding => 'فهم الجمهور',
            self::PositioningMessage => 'التموضع والرسالة',
            self::ChannelStructure => 'البنية القنواتية',
            self::MeasurementData => 'القياس والبيانات',
            self::ExecutionCapacity => 'القدرة التنفيذية',
            self::AiReadiness => 'الجاهزية للذكاء الاصطناعي',
            self::OwnedAssets => 'الأصول المملوكة',
        };
    }

    /**
     * ما يقيسه المحور، بلغة صاحب النشاط لا بلغة المنهجية.
     */
    public function question(): string
    {
        return match ($this) {
            self::StrategicClarity => 'لمن تبيع، ولماذا يُشترى منك',
            self::AudienceUnderstanding => 'دقة تعريفك لشرائح عملائك',
            self::PositioningMessage => 'تمايزك ووضوح عرضك',
            self::ChannelStructure => 'ملاءمة قنواتك لرحلة الشراء',
            self::MeasurementData => 'هل تعرف ما ينجح فعلًا',
            self::ExecutionCapacity => 'فريقك وعملياتك وإيقاعك',
            self::AiReadiness => 'ظهورك في إجابات النماذج وقابلية موقعك للقراءة الآلية',
            self::OwnedAssets => 'اعتمادك على جمهور مستأجر مقابل بيانات مباشرة',
        };
    }

    /**
     * أقصى مستوى دليل يبلغه هذا المحور.
     *
     * المحاور ١–٦ مصدرها ما يقوله المستخدم عن نفسه، فسقفها `inferred` مهما
     * دقّت الحسابات فوقها. المحوران ٧ و٨ يقرآن مصدرًا مستقلًا عنه فيبلغان
     * `measured` — وهذا الفرق هو أساس التسعير لا تفصيلًا عرضيًّا (§٥).
     */
    public function ceiling(): EvidenceLevel
    {
        return match ($this) {
            self::AiReadiness, self::OwnedAssets => EvidenceLevel::Measured,
            default => EvidenceLevel::Inferred,
        };
    }

    /**
     * وزن المحور في `maturity_score`.
     *
     * المحوران المقيسان يزنان أكثر لأن دليلهما مستقل عن صاحب النشاط. مجموع
     * الأوزان لا يعني شيئًا بذاته — المتوسط موزون بأوزان المحاور المفعّلة
     * وحدها، فمحور غير مفعّل يخرج من البسط والمقام معًا.
     */
    public function weight(): float
    {
        return match ($this) {
            self::AiReadiness, self::OwnedAssets => 1.5,
            default => 1.0,
        };
    }

    /**
     * ترتيب العرض: المقيس أولًا.
     *
     * ما يُقاس موضوعيًّا يتصدّر التقرير لأنه ما يمكن للمستخدم أن يثق به بلا
     * تحفّظ، وما يُستنتج من كلامه يليه محمولًا بوسمه.
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::AiReadiness => 1,
            self::OwnedAssets => 2,
            self::StrategicClarity => 3,
            self::AudienceUnderstanding => 4,
            self::PositioningMessage => 5,
            self::ChannelStructure => 6,
            self::MeasurementData => 7,
            self::ExecutionCapacity => 8,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $a->sortOrder() <=> $b->sortOrder());

        return $cases;
    }
}
