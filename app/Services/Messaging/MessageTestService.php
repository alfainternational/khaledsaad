<?php

namespace App\Services\Messaging;

use App\Models\MessageTestBatch;
use App\Models\MessageTestResult;
use App\Models\MessageVariant;
use App\Models\PersonaPanel;
use App\Models\User;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * يختبر إصدارًا واحدًا أو رسائل الشخصيات كلها.
 *
 * كل شخصية تقيّم رسالتها هي فقط: عرض رسالة غيرها عليها يقيس فضولها لا
 * ملاءمة النص لها. والاختبار الجزئي يعرض ما اكتمل ويسمّي من لم يكتمل،
 * ولا يخترع نتيجة لشخصية لم يردّ عنها النموذج.
 */
class MessageTestService
{
    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    /**
     * @param  Collection<int, MessageVariant>  $variants
     */
    public function test(PersonaPanel $panel, Collection $variants, User $user, string $mode): MessageTestBatch
    {
        $variants = $variants->values();

        if ($variants->isEmpty()) {
            throw new RuntimeException('لا توجد رسالة صالحة للاختبار.');
        }

        // إصدار من مشروع آخر لا يُختبر هنا حتى لو مُرّر معرّفه.
        foreach ($variants as $variant) {
            if ($variant->persona_panel_id !== $panel->id) {
                throw new RuntimeException('رسالة لا تخص لوحة هذا المشروع.');
            }
        }

        $profiles = $this->profiles->profiles($panel);
        $keys = $variants->pluck('persona_key')->unique()->values()->all();
        $payload = $this->request($panel, $profiles, $variants, $keys);

        $returned = collect($payload['results'] ?? [])->keyBy('persona_key');
        $missing = array_values(array_diff($keys, $returned->keys()->all()));

        if ($returned->isEmpty()) {
            throw new RuntimeException('تعذّر إجراء الاختبار الآن.');
        }

        return DB::transaction(function () use ($panel, $variants, $user, $mode, $payload, $returned, $missing) {
            $batch = MessageTestBatch::create([
                'project_id' => $panel->project_id,
                'persona_panel_id' => $panel->id,
                'user_id' => $user->id,
                'mode' => $mode,
                'channel' => $variants->first()->channel,
                'objective' => $variants->first()->objective,
                'status' => $missing === []
                    ? MessageTestBatch::STATUS_COMPLETE
                    : MessageTestBatch::STATUS_PARTIAL,
                'summary' => [
                    'comparison' => $payload['summary']['comparison'] ?? null,
                    'next_experiment' => $payload['summary']['next_experiment'] ?? null,
                    // الشخصيات التي لم تكتمل تُسمّى ولا تُملأ بتقدير صامت.
                    'incomplete' => $missing,
                ],
            ]);

            foreach ($variants as $variant) {
                $result = $returned->get($variant->persona_key);

                if ($result === null) {
                    continue;
                }

                MessageTestResult::create([
                    'message_test_batch_id' => $batch->id,
                    'message_variant_id' => $variant->id,
                    'persona_key' => $variant->persona_key,
                    'score' => (int) $result['score'],
                    // فرضية دائمًا: لا مشترٍ حقيقي قرأ هذه الرسالة (§٤.١).
                    'evidence_level' => EvidenceLevel::Inferred,
                    'reaction' => $result['reaction'],
                    'strength' => $result['strength'] ?? null,
                    'objection' => $result['objection'] ?? null,
                    'revised_content' => $result['revised_content'] ?? null,
                ]);

                // المسودة تصير مختبَرة فتُقفل عن التحرير المباشر.
                if ($variant->status === MessageVariant::STATUS_DRAFT) {
                    $variant->update(['status' => MessageVariant::STATUS_TESTED]);
                }
            }

            return $batch->load('results');
        });
    }

    /**
     * إصدار جديد من تعديل مقترح — لا يُكتب فوق المختبَر.
     */
    public function reviseFrom(MessageTestResult $result, User $user): MessageVariant
    {
        $parent = $result->variant;

        return MessageVariant::create([
            'project_id' => $parent->project_id,
            'persona_panel_id' => $parent->persona_panel_id,
            'user_id' => $user->id,
            'persona_key' => $parent->persona_key,
            'channel' => $parent->channel,
            'objective' => $parent->objective,
            'content' => (string) $result->revised_content,
            'origin' => MessageVariant::ORIGIN_REVISED,
            'status' => MessageVariant::STATUS_DRAFT,
            'parent_id' => $parent->id,
            'source_type' => $parent->source_type,
            'source_id' => $parent->source_id,
            'source_context' => $parent->source_context,
            'teaching_note' => $parent->teaching_note,
            'reusable_formula' => $parent->reusable_formula,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     * @param  Collection<int, MessageVariant>  $variants
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function request(PersonaPanel $panel, array $profiles, Collection $variants, array $keys): array
    {
        $pairs = $variants->map(fn (MessageVariant $variant) => [
            'persona_key' => $variant->persona_key,
            'persona' => $profiles[$variant->persona_key] ?? ['persona_key' => $variant->persona_key],
            'message' => $variant->content,
        ])->all();

        try {
            return $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => implode("\n", [
                        'أنت تدير جلسة مجموعة تركيز افتراضية. كل شخصية تقرأ الرسالة المقصودة لها وحدها وتحكم عليها.',
                        'القواعد:',
                        '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                        '2. نتيجة واحدة لكل persona_key مُرسَل، ولا تقيّم شخصية برسالة غيرها.',
                        '3. score من 100 يعكس احتمال تفاعلها فعلًا، لا جودة الكتابة.',
                        '4. strength ما نجح في الرسالة معها تحديدًا، وobjection ما بقي يمنعها.',
                        '5. revised_content تعديل لهذه الشخصية وحدها بنفس القناة والطول تقريبًا — لا نصًّا يصلح للجميع.',
                        '6. summary: comparison يصف الفرق بين الشخصيات، وnext_experiment تجربة واحدة تالية. ولا تكتب رسالة في الخلاصة.',
                    ])],
                    ['role' => 'user', 'content' => 'الأزواج (شخصية ← رسالتها): '
                        .json_encode($pairs, JSON_UNESCAPED_UNICODE)],
                ],
                schema: MessageSchemas::tests($keys),
                tier: 'standard',
                stage: 'message_test',
                salvage: true,
            ));
        } catch (Throwable $exception) {
            Log::warning('message test failed', [
                'panel' => $panel->id,
                'personas' => count($keys),
                'stage' => 'message_test',
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
