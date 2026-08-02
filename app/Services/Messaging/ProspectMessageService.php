<?php

namespace App\Services\Messaging;

use App\Models\PersonaPanel;
use App\Models\Project;
use App\Models\Prospect;
use App\Models\ProspectMessage;
use App\Models\User;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageObjective;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * رسالة مستقلة لكل عميل متوقع بالاسم.
 *
 * الفرق عن رسالة الشخصية: هنا نعرف الشخص فعلًا — مدينته، جهته، وما دار
 * بينكما. فتُبنى الرسالة على ما نعرفه عنه أولًا، وتستعير من أقرب شخصية
 * نبرتها واعتراضها المرجّح حين لا نعرف كفاية.
 *
 * ثلاث قواعد:
 *
 * ١) المفاتيح محصورة والعدد مثبَّت — فيستحيل نصٌّ واحد يُرسَل للجميع
 *    باسم «تخصيص».
 * ٢) ممنوع اختلاق ما لا نعرفه عن الشخص. اسم شركة أو لقاء لم يُذكر في
 *    ملاحظاتك يجعل الرسالة كذبًا يُكتشف في أول رد.
 * ٣) سقف للدفعة الواحدة (§٤.٤): توليد بلا حدّ يستنزف ميزانية المساحة.
 */
class ProspectMessageService
{
    /** أقصى عدد عملاء في طلب واحد. */
    public const BATCH_LIMIT = 10;

    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly PersonaMessageProfileService $profiles,
    ) {}

    /**
     * @param  Collection<int, Prospect>  $prospects
     * @param  array<string, mixed>  $source
     * @return array{messages: array<int, ProspectMessage>, failed: array<int, string>, skipped: int}
     */
    public function generate(
        Project $project,
        Collection $prospects,
        MessageChannel $channel,
        MessageObjective $objective,
        User $user,
        array $source = [],
    ): array {
        // السقف يُطبَّق قبل الاستدعاء لا بعده، ويُعلَن ما تجاوزه.
        $skipped = max(0, $prospects->count() - self::BATCH_LIMIT);
        $batch = $prospects->take(self::BATCH_LIMIT)->values();

        if ($batch->isEmpty()) {
            return ['messages' => [], 'failed' => [], 'skipped' => 0];
        }

        $panel = $project->personaPanel;
        $keys = $batch->map(fn (Prospect $prospect) => $this->keyFor($prospect))->all();

        $returned = collect($this->request($project, $panel, $batch, $keys, $channel, $objective, $source))
            ->keyBy('prospect_key');

        $messages = [];
        $failed = [];

        foreach ($batch as $prospect) {
            $key = $this->keyFor($prospect);
            $payload = $returned->get($key);

            if ($payload === null) {
                // العميل الذي لم تُكتب رسالته يُسمّى ولا تُختلق له نسخة عامة.
                $failed[] = $prospect->name;

                continue;
            }

            $messages[] = $this->store($prospect, $payload, $channel, $objective, $user, $source);
        }

        return ['messages' => $messages, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * مفتاح ثابت للعميل داخل الطلب — لا يعتمد على ترتيب القائمة.
     */
    public function keyFor(Prospect $prospect): string
    {
        return 'p'.$prospect->id;
    }

    /**
     * @param  Collection<int, Prospect>  $prospects
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    private function request(
        Project $project,
        ?PersonaPanel $panel,
        Collection $prospects,
        array $keys,
        MessageChannel $channel,
        MessageObjective $objective,
        array $source,
    ): array {
        $profiles = $panel !== null ? $this->profiles->profiles($panel) : [];

        $briefs = $prospects->map(function (Prospect $prospect) use ($profiles) {
            $brief = $prospect->briefing($this->keyFor($prospect));
            $persona = $profiles[$prospect->persona_key] ?? null;

            // الشخصية تُرسل كمرجع نبرة لا كوصف لهذا الشخص: ما نعرفه عنه يسبقها.
            return $persona === null ? $brief : $brief + [
                'closest_persona_tone' => $persona['tone'] ?? null,
                'closest_persona_objection' => $persona['objection'] ?? null,
                'avoid' => $persona['avoid'] ?? null,
            ];
        })->all();

        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => implode("\n", [
                        'أنت تكتب رسالة موجّهة إلى عميل متوقع بعينه، يعرفه صاحب المشروع شخصيًّا.',
                        'القواعد:',
                        '1. أعد كائن JSON واحدًا فقط دون أي نص خارجه.',
                        '2. عنصر واحد لكل prospect_key مُرسَل — لا تزد ولا تنقص ولا تكرر مفتاحًا.',
                        '3. ممنوع نصٌّ عام يصلح لأكثر من شخص. كل رسالة تذكر ما نعرفه عن صاحبها تحديدًا.',
                        '4. ممنوع اختلاق أي معلومة عنه: لا لقاء لم يُذكر، ولا شركة لم تُذكر، ولا اهتمام لم يُكتب في بياناته.',
                        '5. ابدأ من what_we_know إن وُجد — هو الفرق بين رسالة شخصية ورسالة جماعية مُقنَّعة.',
                        '6. closest_persona_tone وclosest_persona_objection مرجع نبرة واعتراض مرجّح فقط، لا وصفٌ مؤكد له.',
                        "7. القناة: {$channel->label()} — {$channel->hint()} والحد {$channel->maxLength()} محرفًا.",
                        "8. الهدف: {$objective->label()} — {$objective->instruction()}",
                        '9. خاطبه باسمه الأول مرة واحدة فقط، وبلا مبالغة في المجاملة.',
                        '10. why جملة واحدة تشرح لماذا هذه الصياغة له، وتبقى خارج نص الرسالة.',
                    ])],
                    ['role' => 'user', 'content' => implode("\n\n", array_filter([
                        'النشاط: '.json_encode([
                            'name' => $project->name,
                            'offer' => $project->profile?->value_proposition,
                        ], JSON_UNESCAPED_UNICODE),
                        'العملاء المتوقعون: '.json_encode($briefs, JSON_UNESCAPED_UNICODE),
                        $this->sourceBlock($source),
                    ]))],
                ],
                schema: MessageSchemas::prospectMessages($keys, $channel->maxLength()),
                tier: 'standard',
                stage: 'prospect_message',
                salvage: true,
            ));

            return $payload['messages'] ?? [];
        } catch (Throwable $exception) {
            // لا اسم عميل ولا نص رسالة في السجل — العدد يكفي للتشخيص.
            Log::warning('prospect message generation failed', [
                'project' => $project->id,
                'prospects' => count($keys),
                'stage' => 'prospect_message',
                'reason' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     */
    private function store(
        Prospect $prospect,
        array $payload,
        MessageChannel $channel,
        MessageObjective $objective,
        User $user,
        array $source,
    ): ProspectMessage {
        return ProspectMessage::create([
            'prospect_id' => $prospect->id,
            'project_id' => $prospect->project_id,
            'user_id' => $user->id,
            'channel' => $channel->value,
            'objective' => $objective->value,
            'content' => trim($payload['content']),
            'why' => $payload['why'] ?? null,
            'origin' => ProspectMessage::ORIGIN_GENERATED,
            'status' => ProspectMessage::STATUS_DRAFT,
            // التوليد الجديد يشير إلى السابق ولا يمحوه.
            'parent_id' => $prospect->latestMessage()?->id,
            'source_type' => $source['type'] ?? null,
            'source_id' => $source['id'] ?? null,
            'source_context' => $source['context'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function sourceBlock(array $source): ?string
    {
        $context = $source['context'] ?? null;

        return blank($context) ? null
            : 'حقائق مؤكدة من تقرير المشروع (استعملها ولا تُضف إليها): '
                .json_encode($context, JSON_UNESCAPED_UNICODE);
    }
}
