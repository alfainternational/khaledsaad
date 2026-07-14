<?php

namespace App\Application\AI;

use App\Contracts\AiGatewayInterface;
use App\Support\Tooling\ToolBlueprintCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * المُحاوِر الذكي (المرحلة 3): يرفع جودة الإدخال بطرح سؤال متابعة واحد حاد عندما
 * تكون إجابة المستخدم عامة أو رخوة، ليستخرج تفصيلاً ملموساً (رقم/مثال/اسم) بدل
 * قبول كلام فضفاض. جوهر «دقّة الإدخال»: لا نكتفي بملء الحقل بل نحسّن مضمونه.
 *
 * يتدهور بأمان: بلا LLM يُرجع null (فتبقى تلميحات الجودة المحلية كما هي في الواجهة).
 * لا يُخزّن شيئاً ولا يعدّل إجابة المستخدم — يقترح فقط.
 */
class ChallengeToolAnswerAction
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly ToolBlueprintCatalog $blueprintCatalog,
    ) {}

    /**
     * يعيد سؤال متابعة واحداً، أو null إذا كانت الإجابة محددة كفايةً أو تعذّر التوليد.
     */
    public function handle(string $toolCode, string $mode, string $fieldKey, string $answer): ?string
    {
        $answer = trim($answer);
        if ($answer === '' || mb_strlen($answer) < 3) {
            return null;
        }

        $labels = $this->blueprintCatalog->fieldLabelMap($toolCode, $mode);
        $meta = $labels[$fieldKey] ?? null;
        if ($meta === null) {
            return null;
        }

        $label = (string) ($meta['label'] ?? $fieldKey);

        $prompt = implode("\n", [
            'أنت مستشار تسويقي يراجع إجابة مستخدم على سؤال أداة، لرفع دقّتها.',
            'السؤال: '.$label,
            'إجابة المستخدم: "'.$answer.'"',
            '',
            'إن كانت الإجابة محددة وملموسة كفايةً (فيها رقم أو مثال أو اسم أو تفصيل واضح)، أعد الكلمة: OK',
            'وإلا اطرح سؤال متابعة واحداً قصيراً (سطر واحد) يدفعه ليضيف التفصيل الأهم الناقص (رقم/مثال/اسم/زمن).',
            'أعد السؤال فقط بالعربية بلا مقدمات ولا شرح، أو OK.',
        ]);

        $system = 'أنت محاور دقيق يرفع جودة إجابات المستخدم بسؤال واحد حاد. لا تجامل ولا تشرح.';

        try {
            $raw = $this->gateway->generateText($prompt, $system);
        } catch (\Throwable $e) {
            Log::warning('Answer challenge failed: '.$e->getMessage());

            return null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $question = trim(strip_tags($raw));
        $question = trim($question, "\"'“”«»");

        if ($question === '' || Str::upper($question) === 'OK' || Str::startsWith(Str::upper($question), 'OK')) {
            return null;
        }

        // حارس: نتيجة طويلة جداً غالباً شرح لا سؤال — نقتصر على أول سطر.
        $firstLine = trim((string) strtok($question, "\n"));

        return $firstLine !== '' ? Str::limit($firstLine, 220, '') : null;
    }
}
