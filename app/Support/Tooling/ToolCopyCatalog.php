<?php

namespace App\Support\Tooling;

use App\Domain\Tool\Models\Tool;

class ToolCopyCatalog
{
    public function submitLabel(): string
    {
        return 'حفظ المخرج الآن';
    }

    public function modeLockedMessage(): string
    {
        return 'هذا المستوى غير متاح بعد. ابدأ بالمستوى المناسب أولاً ثم أعد المحاولة.';
    }

    public function successMessageForTool(Tool $tool): string
    {
        $name = $tool->name ?: $tool->code;

        return 'تم حفظ مخرج أداة "'.$name.'" بنجاح. راجع الخلاصة ثم أكمل الخطوة التالية.';
    }
}
