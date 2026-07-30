<?php

namespace App\Modules\Competitors\AdLibraries;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * نتيجة سحب واحدة: منصة واحدة لمنافس واحد، بحالتها ومصدرها وتاريخها.
 *
 * الحالة هي جوهر الأمانة (§٤.٣): `fetched` تحمل إعلانات، و`unavailable`/`broke`
 * تحملان ملاحظة تغطية بلا إعلانات — فالفرق بين «لم نستطع السحب» و«لا إعلانات
 * له» يبقى ظاهرًا. خلطهما يجعل عطلًا تقنيًّا يُقرأ حكمًا على المنافس.
 */
final class AdSnapshot
{
    public const FETCHED = 'fetched';

    public const UNAVAILABLE = 'unavailable';

    public const BROKE = 'broke';

    /**
     * @param  array<int, array<string, mixed>>  $ads  إعلانات مرصودة، كلٌّ بنصّه الخام
     */
    private function __construct(
        public readonly string $platform,
        public readonly string $status,
        public readonly array $ads,
        public readonly ?string $sourceUrl,
        public readonly ?string $coverageNote,
        public readonly CarbonInterface $capturedAt,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $ads
     */
    public static function fetched(string $platform, array $ads, string $sourceUrl, ?CarbonInterface $at = null): self
    {
        return new self(
            platform: $platform,
            status: self::FETCHED,
            ads: array_values($ads),
            sourceUrl: $sourceUrl,
            coverageNote: null,
            capturedAt: $at ? CarbonImmutable::parse($at) : CarbonImmutable::now(),
        );
    }

    /** المنصة بلا مصدر سحب مضبوط: تغطية غائبة معلنة، لا فشل صامت. */
    public static function unavailable(string $platform, string $note, ?CarbonInterface $at = null): self
    {
        return new self(
            platform: $platform,
            status: self::UNAVAILABLE,
            ads: [],
            sourceUrl: null,
            coverageNote: $note,
            capturedAt: $at ? CarbonImmutable::parse($at) : CarbonImmutable::now(),
        );
    }

    /** السحب تكسّر: المصدر موجود لكن الصفحة تغيّرت. تغطية ناقصة لا صفر إعلانات. */
    public static function broke(string $platform, string $note, ?string $sourceUrl = null, ?CarbonInterface $at = null): self
    {
        return new self(
            platform: $platform,
            status: self::BROKE,
            ads: [],
            sourceUrl: $sourceUrl,
            coverageNote: $note,
            capturedAt: $at ? CarbonImmutable::parse($at) : CarbonImmutable::now(),
        );
    }

    public function isFetched(): bool
    {
        return $this->status === self::FETCHED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'platform' => $this->platform,
            'status' => $this->status,
            'source_url' => $this->sourceUrl,
            'captured_at' => $this->capturedAt,
            'ads' => $this->ads,
            'coverage_note' => $this->coverageNote,
        ];
    }
}
