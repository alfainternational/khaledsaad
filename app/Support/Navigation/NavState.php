<?php

declare(strict_types=1);

namespace App\Support\Navigation;

enum NavState: string
{
    /** مبني وقابل للفتح. */
    case Available = 'available';

    /** غير مبني بعد — يُرسم معطّلًا بشارة معلنة، ولا يُوجَّه لبديل. */
    case ComingSoon = 'coming_soon';

    /** يحتاج خطة أو صلاحية لا يملكها هذا المستخدم. */
    case Locked = 'locked';
}
