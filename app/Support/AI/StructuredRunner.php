<?php

namespace App\Support\AI;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIInvalidOutputException;
use App\Models\AiUsageRecord;
use App\Models\ToolRun;

/**
 * الطبقة التي تفصل «استدعاء نموذج» عن «الحصول على بيانات صالحة».
 *
 * كل استدعاء منظم يمر من هنا حتى يتحقق ثلاثة أشياء دائمًا:
 * تحقق المخطط، إعادة المحاولة المصححة، وتسجيل التكلفة في ai_usage_records.
 */
class StructuredRunner
{
    public function __construct(
        private readonly ArtificialIntelligenceGateway $gateway,
        private readonly JsonSchemaValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AIInvalidOutputException عندما يفشل المخرج بعد استنفاد المحاولات.
     */
    public function run(AIRequest $request, ?ToolRun $toolRun = null): array
    {
        $maxAttempts = max(1, (int) config('ai.schema_retries', 2) + 1);
        $violations = [];
        $current = $request;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $this->gateway->run($current);

            $this->record($response, $toolRun, 'success');

            try {
                $payload = $response->decoded();
            } catch (AIInvalidOutputException $exception) {
                $violations = ['المخرج ليس JSON صالحًا.'];
                $current = $this->correct($request, $violations);

                continue;
            }

            $violations = $request->jsonSchema
                ? $this->validator->validate($payload, $request->jsonSchema)
                : [];

            if ($violations === []) {
                return $payload;
            }

            // آخر محاولة فشلت: قبل الاستسلام، حاول إنقاذ ما صحّ من المخرج.
            if ($attempt === $maxAttempts && $request->salvage) {
                $salvaged = $this->salvage($payload, $request->jsonSchema);

                if ($salvaged !== null) {
                    return $salvaged;
                }
            }

            // إعادة المحاولة تُبنى على الطلب الأصلي مضافًا إليه المخالفات،
            // لا على المحاولة السابقة، حتى لا تتراكم التصحيحات وتشوّه السياق.
            $current = $this->correct($request, $violations);
        }

        if ($toolRun !== null) {
            AiUsageRecord::where('tool_run_id', $toolRun->id)
                ->where('stage', $request->stage)
                ->latest('id')
                ->limit(1)
                ->update(['status' => 'invalid_output']);
        }

        throw new AIInvalidOutputException(
            'تعذر الحصول على مخرج مطابق للمخطط بعد استنفاد المحاولات.',
            $violations,
        );
    }

    /**
     * إنقاذ مخرج فشل تحققه: نُبقي عناصر المصفوفات الصالحة ونحذف المعطوبة،
     * فبدل إسقاط خمس نتائج بسبب واحدة معطوبة، نحتفظ بالأربع السليمة.
     *
     * يقبل فقط حين تُنتج نسخة مقلّمة كائنًا مطابقًا للمخطط (بحد أدنى عنصر واحد).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>|null
     */
    private function salvage(array $payload, ?array $schema): ?array
    {
        if ($schema === null || ($schema['type'] ?? null) !== 'object') {
            return null;
        }

        $pruned = $payload;

        foreach ($schema['properties'] ?? [] as $key => $childSchema) {
            if (($childSchema['type'] ?? null) !== 'array' || ! isset($pruned[$key]) || ! is_array($pruned[$key])) {
                continue;
            }

            $itemSchema = $childSchema['items'] ?? null;

            if ($itemSchema === null) {
                continue;
            }

            $pruned[$key] = array_values(array_filter(
                $pruned[$key],
                fn ($item) => $this->validator->validate($item, $itemSchema) === [],
            ));
        }

        // النسخة المقلّمة تُقبل فقط إن صارت مطابقة تمامًا (مع حد أدنى عنصر واحد
        // حيث كان المخطط يطلب أكثر): لا نُرجع مخرجًا ما زال مخالفًا.
        return $this->validator->validate($pruned, $this->relaxMinItems($schema)) === []
            ? $pruned
            : null;
    }

    /**
     * يخفّض minItems إلى 1 حيثما وُجد: الإنقاذ يقبل «أقل لكن صحيح».
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function relaxMinItems(array $schema): array
    {
        if (($schema['type'] ?? null) === 'array' && isset($schema['minItems'])) {
            $schema['minItems'] = min(1, (int) $schema['minItems']);
        }

        foreach ($schema['properties'] ?? [] as $key => $child) {
            $schema['properties'][$key] = $this->relaxMinItems($child);
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->relaxMinItems($schema['items']);
        }

        return $schema;
    }

    /**
     * @param  array<int, string>  $violations
     */
    private function correct(AIRequest $request, array $violations): AIRequest
    {
        $notice = "المخرج السابق خالف المخطط في النقاط التالية:\n- "
            .implode("\n- ", $violations)
            ."\n\nأعد إنتاج الكائن كاملًا بصيغة JSON صحيحة تطابق المخطط تمامًا، دون أي نص خارج الكائن.";

        return $request->withMessages([
            ...$request->messages,
            ['role' => 'user', 'content' => $notice],
        ]);
    }

    private function record(AIResponse $response, ?ToolRun $toolRun, string $status): void
    {
        AiUsageRecord::create([
            'tool_run_id' => $toolRun?->id,
            ...$response->usageRecord(),
            'status' => $status,
        ]);
    }
}
