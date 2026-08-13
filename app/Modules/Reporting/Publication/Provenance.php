<?php

namespace App\Modules\Reporting\Publication;

enum Provenance: string
{
    case Automated = 'automated';
    case Signed = 'signed';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Automated => 'تحليل آلي بقواعد ثابتة',
            self::Signed => 'تحليل موقّع من خالد سعد',
            self::Hybrid => 'تحليل آلي راجعه خالد سعد',
        };
    }
}
