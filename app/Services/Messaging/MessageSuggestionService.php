<?php

namespace App\Services\Messaging;

use App\Models\MessageVariant;
use App\Models\PersonaPanel;
use App\Models\User;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * يقترح مسودة مستقلة لكل شخصية مطلوبة.
 *
 * لا يقبل `overall_message` ولا ينتجه: العقد نفسه يمنعه (عنصر لكل مفتاح،
 * والمفاتيح محصورة). المسودة اقتراح قابل للتحرير لا قرارًا نهائيًّا.
 *
 * فشل جزء من الدفعة لا يُسقطها: ما نجح يُحفظ مسودات، وما فشل يُعاد طلبه
 * وحده. إسقاط الدفعة كلها لفشل شخصية يُضيّع عملًا سليمًا بلا سبب.
 */
class MessageSuggestionService
{
    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    /**
     * @param  array<int, string>  $personaKeys
     * @param  array<string, mixed>  $source  نوع المصدر ومعرفه وسياقه المحدود
     * @return array{variants: array<int, MessageVariant>, failed: array<int, string>}
     */
    public function suggest(
        PersonaPanel $panel,
        array $personaKeys,
        MessageChannel $channel,
        MessageObjective $objective,
        User $user,
        array $source = [],
    ): array {
        $profiles = $this->profiles->profiles($panel);
        $wanted = array_values(array_intersect(array_keys($profiles), $personaKeys));

        if ($wanted === []) {
            return ['variants' => [], 'failed' => []];
        }

        $messages = $this->request($panel, $profiles, $wanted, $channel, $objective, $source);

        // إعادة المحاولة للناقص وحده — لا للدفعة كاملة.
        $missing = array_values(array_diff($wanted, array_column($messages, 'persona_key')));

        if ($missing !== []) {
            $messages = array_merge(
                $messages,
                $this->request($panel, $profiles, $missing, $channel, $objective, $source),
            );
        }

        $variants = [];
        $seen = [];

        foreach ($messages as $message) {
            $key = $message['persona_key'];

            // مفتاح مكرر يعني رسالتين لشخصية واحدة — نأخذ الأولى ونترك الثانية.
            if (! in_array($key, $wanted, true) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $variants[] = $this->store($panel, $key, $message, $channel, $objective, $user, $source);
        }

        return [
            'variants' => $variants,
            'failed' => array_values(array_diff($wanted, array_keys($seen))),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $profiles
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    private function request(
        PersonaPanel $panel,
        array $profiles,
        array $keys,
        MessageChannel $channel,
        MessageObjective $objective,
        array $source,
    ): array {
        $requested = array_map(fn (string $key) => $profiles[$key], $keys);

        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => implode("\n", [
                        'أنت كاتب إعلانات عربي يكتب رسالة مستقلة لكل شخصية عميل.',
                        'القواعد:',
                        '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                        '2. عنصر واحد لكل persona_key مُرسَل — لا تزد ولا تنقص ولا تكرر مفتاحًا.',
                        '3. ممنوع إنتاج رسالة عامة تصلح لأكثر من شخصية، وممنوع دمج مزايا الشخصيات في نص واحد.',
                        "4. القناة: {$channel->label()} — {$channel->hint()} والحد {$channel->maxLength()} محرفًا.",
                        "5. الهدف: {$objective->label()} — {$objective->instruction()}",
                        '6. اكتب بنبرة الشخصية (tone) ومفرداتها، وعالج دافعها (motivation) واعتراضها (objection) وحدهما.',
                        '7. احترم حقل avoid حرفيًّا.',
                        '8. content نصٌّ جاهز للنشر — لا وصفًا لما ينبغي كتابته ولا عناوين بديلة داخله.',
                        '9. teaching_note جملتان بحد أقصى تشرحان سبب الصياغة، وتبقيان خارج النص.',
                        '10. reusable_formula سطر واحد مثل: [الوجع] + [الدليل] + [الإجراء].',
                    ])],
                    ['role' => 'user', 'content' => implode("\n\n", array_filter([
                        'الشخصيات المطلوبة: '.json_encode($requested, JSON_UNESCAPED_UNICODE),
                        $this->sourceBlock($source),
                    ]))],
                ],
                schema: MessageSchemas::suggestions($keys, $channel->maxLength()),
                tier: 'standard',
                stage: 'message_suggestion',
                salvage: true,
            ));

            return $payload['messages'] ?? [];
        } catch (Throwable $exception) {
            // لا يُسجَّل نص الرسالة — السياق يكفي للتشخيص.
            Log::warning('message suggestion failed', [
                'panel' => $panel->id,
                'personas' => count($keys),
                'stage' => 'message_suggestion',
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $source
     */
    private function store(
        PersonaPanel $panel,
        string $key,
        array $message,
        MessageChannel $channel,
        MessageObjective $objective,
        User $user,
        array $source,
    ): MessageVariant {
        return MessageVariant::create([
            'project_id' => $panel->project_id,
            'persona_panel_id' => $panel->id,
            'user_id' => $user->id,
            'persona_key' => $key,
            'channel' => $channel->value,
            'objective' => $objective->value,
            'content' => trim($message['content']),
            'origin' => MessageVariant::ORIGIN_SUGGESTED,
            'status' => MessageVariant::STATUS_DRAFT,
            'source_type' => $source['type'] ?? null,
            'source_id' => $source['id'] ?? null,
            'source_context' => $source['context'] ?? null,
            'teaching_note' => $message['teaching_note'] ?? null,
            'reusable_formula' => $message['reusable_formula'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function sourceBlock(array $source): ?string
    {
        $context = $source['context'] ?? null;

        if (blank($context)) {
            return null;
        }

        // حقائق مؤكدة فقط — لا يُمرَّر التقرير كاملًا ولا يُنقل افتراض كحقيقة.
        return 'حقائق مؤكدة من تقرير المشروع (استعملها ولا تُضف إليها): '
            .json_encode($context, JSON_UNESCAPED_UNICODE);
    }
}
