<?php

declare(strict_types=1);

namespace App\Support\AI\Resilience;

use RuntimeException;
use Throwable;

/**
 * إشارة داخلية: سقط المزوّد **بعد** أن بدأ النص يصل إلى المستخدم.
 *
 * وجودها يمنع السلسلة من إكمال نصٍّ بنصّ مزوّد آخر — وهو ما يُنتج تقريرًا
 * نصفه بأسلوب ونصفه بآخر، وربما بمعلومتين متناقضتين في فقرة واحدة.
 */
final class StreamAlreadyStartedException extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('انقطع التدفق بعد بدء العرض.', 0, $previous);
    }
}
