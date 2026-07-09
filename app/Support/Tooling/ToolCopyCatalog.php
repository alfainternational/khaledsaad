<?php

namespace App\Support\Tooling;

use App\Domain\Tool\Models\Tool;

class ToolCopyCatalog
{
    public function submitLabel(): string
    {
        return 'احفظ النتيجة الآن';
    }

    public function modeLockedMessage(): string
    {
        return 'هذا المستوى غير متاح بعد. ابدأ بالمستوى المناسب أولاً ثم أعد المحاولة.';
    }

    public function successMessageForTool(Tool $tool): string
    {
        $name = $tool->name ?: $tool->code;

        return 'حفظنا نتيجة أداة "'.$name.'". راجع الخلاصة ثم أكمل خطوتك التالية.';
    }
}
