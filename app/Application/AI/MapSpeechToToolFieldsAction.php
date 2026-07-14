<?php

namespace App\Application\AI;

use App\Contracts\AiGatewayInterface;
use App\Support\Tooling\ToolBlueprintCatalog;
use Illuminate\Support\Facades\Log;

/**
 * الطبقة الثانية من إدخال الصوت (المرحلة 2): توزيع النص المُفرّغ على حقول الأداة.
 *
 * المستخدم يتكلّم بحرية بلهجته، فيفهم الذكاء النيّة ويوزّعها على الحقول المنظّمة —
 * وهو ما يسامح أخطاء التفريغ لأن الفهم من السياق. إجابة صوتية واحدة تملأ عدّة حقول.
 *
 * يتدهور بأمان: بلا LLM (أو فشل التحليل) يُرجع النص الخام في أول حقل حتى لا يضيع
 * ما قاله المستخدم — فالمستخدم يعدّل بدل أن يفقد كلامه.
 */
class MapSpeechToToolFieldsAction
{
    public function __construct(
        private readonly AiGatewayInterface $gateway,
        private readonly ToolBlueprintCatalog $blueprintCatalog,
    ) {}

    /**
     * @return array{fields: array<string, string>, ai_mapped: bool}
     */
    public function handle(string $toolCode, string $mode, string $transcript): array
    {
        $transcript = trim($transcript);
        $labels = $this->blueprintCatalog->fieldLabelMap($toolCode, $mode);

        if ($transcript === '' || $labels === []) {
            return ['fields' => [], 'ai_mapped' => false];
        }

        $mapped = $this->mapWithAi($toolCode, $labels, $transcript);
        if ($mapped !== null) {
            return ['fields' => $mapped, 'ai_mapped' => true];
        }

        // تدهور آمن: ضع كل النص في أول حقل بدل فقدانه.
        $firstKey = (string) array_key_first($labels);

        return ['fields' => [$firstKey => $transcript], 'ai_mapped' => false];
    }

    /**
     * @param  array<string, array{label: string, answer_tip: string}>  $labels
     * @return array<string, string>|null
     */
    private function mapWithAi(string $toolCode, array $labels, string $transcript): ?array
    {
        $fieldLines = [];
        foreach ($labels as $key => $meta) {
            $tip = trim((string) ($meta['answer_tip'] ?? ''));
            $fieldLines[] = '- '.$key.': '.(string) ($meta['label'] ?? $key).($tip !== '' ? ' ('.$tip.')' : '');
        }

        $allowedKeys = array_keys($labels);

        $prompt = implode("\n", [
            'حوّل كلام المستخدم التالي (قد يكون بلهجة عامية) إلى إجابات منظّمة على حقول أداة تسويقية.',
            'وزّع المعنى على الحقول المناسبة، وصحّح أخطاء التفريغ الواضحة من السياق، وبالعربية الفصحى المهنية.',
            'لا تخترع معلومات غير موجودة في كلامه؛ الحقل الذي لا يذكره اتركه فارغاً ("").',
            '',
            'الحقول المتاحة (المفتاح: الوصف):',
            ...$fieldLines,
            '',
            'كلام المستخدم:',
            '"""'.$transcript.'"""',
            '',
            'أعِد JSON فقط بهذا الشكل بلا أي شرح: {"'.implode('": "", "', $allowedKeys).'": ""}',
        ]);

        $system = 'أنت مساعد يحوّل الكلام الحر إلى حقول منظّمة. تُعيد JSON صالحاً فقط، بمفاتيح إنجليزية محدّدة وقيم عربية.';

        try {
            $raw = $this->gateway->generateText($prompt, $system);
        } catch (\Throwable $e) {
            Log::warning('Speech field mapping failed: '.$e->getMessage());

            return null;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = $this->decodeJson($raw);
        if (! is_array($decoded)) {
            return null;
        }

        $allowed = array_flip($allowedKeys);
        $fields = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key) || ! isset($allowed[$key]) || ! is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $fields[$key] = $value;
            }
        }

        return $fields === [] ? null : $fields;
    }

    private function decodeJson(string $raw): mixed
    {
        $cleaned = preg_replace('/^```json\s*/i', '', trim($raw));
        $cleaned = preg_replace('/^```\s*/i', '', (string) $cleaned);
        $cleaned = preg_replace('/\s*```$/i', '', (string) $cleaned);
        $cleaned = trim((string) $cleaned);

        // التقط أول كائن JSON إن أحاطه المزوّد بنص.
        if (! str_starts_with($cleaned, '{') && preg_match('/\{.*\}/s', $cleaned, $m)) {
            $cleaned = $m[0];
        }

        return json_decode($cleaned, true);
    }
}
