<?php

namespace App\Modules\Insights;

/**
 * البلد المرجَّح — `inferred` دائمًا وبلا استثناء (§٤.١).
 *
 * قرار «كله داخلي» يعني بلا قاعدة GeoIP خارجية، فلا مصدر لدينا يقول
 * أين الزائر يقينًا. المتاح إشارتان يعلنهما المتصفح بنفسه: المنطقة
 * الزمنية ولغة النظام. المنطقة الزمنية أدقّ («Asia/Riyadh» تعني السعودية
 * بلا لبس)، واللغة أضعف («ar-SA» قد تكون سعوديًّا في لندن).
 *
 * ولذلك يُخزَّن أساس الاستنتاج مع النتيجة، وتُعرض في اللوحة موسومة
 * «فرضية» مع أساسها. الخطر ليس أن نُخطئ البلد — الخطر أن نعرضه كحقيقة.
 */
class LocationInference
{
    /**
     * المناطق الزمنية ⇐ رمز البلد. الخليج والعالم العربي بالتفصيل،
     * وما بعده بالعواصم الشائعة — لأن أسئلة اللوحة عن السوق المستهدف.
     *
     * @var array<string, string>
     */
    private const ZONES = [
        'Asia/Riyadh' => 'SA',
        'Asia/Dubai' => 'AE',
        'Asia/Kuwait' => 'KW',
        'Asia/Qatar' => 'QA',
        'Asia/Bahrain' => 'BH',
        'Asia/Muscat' => 'OM',
        'Asia/Aden' => 'YE',
        'Asia/Baghdad' => 'IQ',
        'Asia/Amman' => 'JO',
        'Asia/Beirut' => 'LB',
        'Asia/Damascus' => 'SY',
        'Asia/Jerusalem' => 'PS',
        'Asia/Gaza' => 'PS',
        'Asia/Hebron' => 'PS',
        'Africa/Cairo' => 'EG',
        'Africa/Khartoum' => 'SD',
        'Africa/Tripoli' => 'LY',
        'Africa/Tunis' => 'TN',
        'Africa/Algiers' => 'DZ',
        'Africa/Casablanca' => 'MA',
        'Africa/Nouakchott' => 'MR',
        'Africa/Mogadishu' => 'SO',
        'Africa/Djibouti' => 'DJ',
        'Africa/Lagos' => 'NG',
        'Africa/Nairobi' => 'KE',
        'Africa/Johannesburg' => 'ZA',
        'Europe/London' => 'GB',
        'Europe/Paris' => 'FR',
        'Europe/Berlin' => 'DE',
        'Europe/Madrid' => 'ES',
        'Europe/Rome' => 'IT',
        'Europe/Istanbul' => 'TR',
        'Europe/Moscow' => 'RU',
        'Europe/Amsterdam' => 'NL',
        'Europe/Stockholm' => 'SE',
        'Europe/Kyiv' => 'UA',
        'America/New_York' => 'US',
        'America/Chicago' => 'US',
        'America/Denver' => 'US',
        'America/Los_Angeles' => 'US',
        'America/Toronto' => 'CA',
        'America/Vancouver' => 'CA',
        'America/Mexico_City' => 'MX',
        'America/Sao_Paulo' => 'BR',
        'Asia/Karachi' => 'PK',
        'Asia/Kolkata' => 'IN',
        'Asia/Calcutta' => 'IN',
        'Asia/Dhaka' => 'BD',
        'Asia/Jakarta' => 'ID',
        'Asia/Kuala_Lumpur' => 'MY',
        'Asia/Singapore' => 'SG',
        'Asia/Manila' => 'PH',
        'Asia/Shanghai' => 'CN',
        'Asia/Tokyo' => 'JP',
        'Asia/Seoul' => 'KR',
        'Asia/Tehran' => 'IR',
        'Australia/Sydney' => 'AU',
    ];

    /** أسماء البلدان بالعربية — العرض عربي كامل (§١٣). */
    private const NAMES = [
        'SA' => 'السعودية', 'AE' => 'الإمارات', 'KW' => 'الكويت', 'QA' => 'قطر',
        'BH' => 'البحرين', 'OM' => 'عُمان', 'YE' => 'اليمن', 'IQ' => 'العراق',
        'JO' => 'الأردن', 'LB' => 'لبنان', 'SY' => 'سوريا', 'PS' => 'فلسطين',
        'EG' => 'مصر', 'SD' => 'السودان', 'LY' => 'ليبيا', 'TN' => 'تونس',
        'DZ' => 'الجزائر', 'MA' => 'المغرب', 'MR' => 'موريتانيا', 'SO' => 'الصومال',
        'DJ' => 'جيبوتي', 'KM' => 'جزر القمر',
        'GB' => 'بريطانيا', 'FR' => 'فرنسا', 'DE' => 'ألمانيا', 'ES' => 'إسبانيا',
        'IT' => 'إيطاليا', 'TR' => 'تركيا', 'RU' => 'روسيا', 'NL' => 'هولندا',
        'SE' => 'السويد', 'UA' => 'أوكرانيا',
        'US' => 'الولايات المتحدة', 'CA' => 'كندا', 'MX' => 'المكسيك', 'BR' => 'البرازيل',
        'PK' => 'باكستان', 'IN' => 'الهند', 'BD' => 'بنغلاديش', 'ID' => 'إندونيسيا',
        'MY' => 'ماليزيا', 'SG' => 'سنغافورة', 'PH' => 'الفلبين', 'CN' => 'الصين',
        'JP' => 'اليابان', 'KR' => 'كوريا الجنوبية', 'IR' => 'إيران', 'AU' => 'أستراليا',
        'NG' => 'نيجيريا', 'KE' => 'كينيا', 'ZA' => 'جنوب أفريقيا',
    ];

    /**
     * @return array{country: string|null, basis: string|null, evidence: string}
     */
    public function resolve(?string $timezone, ?string $acceptLanguage): array
    {
        if ($timezone !== null && isset(self::ZONES[$timezone])) {
            return ['country' => self::ZONES[$timezone], 'basis' => 'timezone', 'evidence' => 'inferred'];
        }

        $fromLanguage = $this->countryFromLanguage($acceptLanguage);

        if ($fromLanguage !== null) {
            return ['country' => $fromLanguage, 'basis' => 'language', 'evidence' => 'inferred'];
        }

        // لا إشارة = لا بلد. تعبئة الفراغ بتقدير صامت ممنوعة (§٤.٣).
        return ['country' => null, 'basis' => null, 'evidence' => 'inferred'];
    }

    /** «ar-SA,ar;q=0.9,en;q=0.8» ⇐ SA. اللغة وحدها بلا بلد لا تكفي. */
    private function countryFromLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        if (preg_match('/[a-z]{2,3}-([A-Z]{2})/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** اللغة الأساسية المعلنة، للعرض والتقسيم. */
    public function primaryLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $first = trim(explode(',', $header)[0]);
        $first = trim(explode(';', $first)[0]);

        return $first !== '' ? mb_substr($first, 0, 20) : null;
    }

    public static function countryName(?string $code): string
    {
        if ($code === null || $code === '') {
            return __('غير معروف');
        }

        return self::NAMES[$code] ?? $code;
    }
}
