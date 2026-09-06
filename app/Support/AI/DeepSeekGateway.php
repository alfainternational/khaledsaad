<?php

namespace App\Support\AI;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Exceptions\AIProviderException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekGateway implements ArtificialIntelligenceGateway
{
    /**
     * ملفّ الإعداد الذي تقرأ منه هذه البوابة.
     *
     * المزوّدات المتوافقة مع واجهة OpenAI (DeepSeek وGroq وCerebras
     * وغيرها) تشترك في الشكل نفسه، فالفرق بينها إعدادٌ لا كود. جعلُ
     * الملفّ معاملًا هو ما يسمح ببناء سلسلة احتياطية دون نسخ هذا الصنف
     * لكل مزوّد — ونسخُه كان سيعني إصلاح كل عطلٍ في أربعة مواضع.
     */
    public function __construct(protected readonly string $profile = 'deepseek') {}

    public function provider(): string
    {
        return $this->profile;
    }

    public function modelForTier(string $tier): string
    {
        $tiers = config("ai.{$this->profile}.tiers", []);

        return $tiers[$tier] ?? config("ai.{$this->profile}.model");
    }

    public function run(AIRequest $request): AIResponse
    {
        $model = $request->model ?? $this->modelForTier($request->tier);
        $startedAt = hrtime(true);

        try {
            $payload = $this->client()
                ->post('/chat/completions', $this->body($request, $model))
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw $this->wrap($exception);
        }

        return $this->toResponse($request, $payload, $model, $startedAt);
    }

    public function stream(AIRequest $request, Closure $onChunk): AIResponse
    {
        $model = $request->model ?? $this->modelForTier($request->tier);
        $startedAt = hrtime(true);

        $body = $this->body($request, $model) + [
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];

        try {
            $response = $this->client()
                ->withOptions(['stream' => true])
                ->post('/chat/completions', $body)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw $this->wrap($exception);
        }

        $content = '';
        $usage = [];
        $finishReason = null;
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);

            while (($breakAt = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $breakAt));
                $buffer = substr($buffer, $breakAt + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    break 2;
                }

                $chunk = json_decode($data, true);

                if (! is_array($chunk)) {
                    continue;
                }

                $usage = $chunk['usage'] ?? $usage;
                $finishReason = $chunk['choices'][0]['finish_reason'] ?? $finishReason;
                $delta = $chunk['choices'][0]['delta']['content'] ?? '';

                if ($delta !== '') {
                    $content .= $delta;
                    $onChunk($delta);
                }
            }
        }

        return $this->toResponse(
            $request,
            [
                'choices' => [['message' => ['content' => $content], 'finish_reason' => $finishReason]],
                'model' => $model,
                'usage' => $usage,
            ],
            $model,
            $startedAt,
        );
    }

    private function client(): PendingRequest
    {
        $config = config("ai.{$this->profile}");

        return Http::baseUrl($config['base_url'])
            ->withToken($config['api_key'])
            ->acceptJson()
            ->timeout($config['timeout'])
            // انقطاع عابر لا يستحق إسقاط تقرير كامل. الأخطاء المنطقية
            // (مفتاح خاطئ، حد استخدام) لا تُعاد لأنها لن تتغير بالإعادة.
            ->retry(
                (int) config('ai.transport_retries', 2),
                (int) config('ai.transport_retry_sleep_ms', 1500),
                fn ($exception) => $exception instanceof ConnectionException,
                throw: true,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function body(AIRequest $request, string $model): array
    {
        $body = [
            'model' => $model,
            'messages' => $request->messages,
            'temperature' => $request->temperature,
        ];

        if ($request->maxTokens !== null) {
            $body['max_tokens'] = $request->maxTokens;
        }

        // وضع JSON إلزامي لكل مرحلة منظمة. بدونه لا يوجد ضمان لشكل المخرج،
        // ويتحول كل تحقق لاحق إلى تخمين.
        if ($request->expectsJson) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        // json_object يضمن JSON صالحًا فقط، لا شكلًا معينًا. بدون إرسال المخطط
        // كنا نحاسب النموذج على عقد لم يره، فيخترع أسماء مفاتيح ويُرفض مخرجه.
        if ($request->jsonSchema !== null) {
            $body['messages'][] = [
                'role' => 'system',
                'content' => $this->schemaInstruction($request->jsonSchema),
            ];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function schemaInstruction(array $schema): string
    {
        return implode("\n", [
            'يجب أن يطابق مخرجك مخطط JSON التالي مطابقة حرفية:',
            '',
            json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            '',
            'قواعد إلزامية على الشكل:',
            '- استخدم أسماء المفاتيح الإنجليزية كما وردت في المخطط حرفيًا. لا تترجمها ولا تستبدلها.',
            '- كل مفتاح مذكور في required يجب أن يوجد في كل عنصر، حتى لو كانت قيمته تقديرية.',
            '- القيم داخل enum تُكتب بالإنجليزية كما هي؛ أما النصوص الحرة فبالعربية.',
            '- لا تضف مفاتيح خارج المخطط، ولا تُرجع مصفوفة في موضع كائن.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toResponse(AIRequest $request, array $payload, string $model, int $startedAt): AIResponse
    {
        $usage = $payload['usage'] ?? [];
        $inputTokens = (int) ($usage['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($usage['completion_tokens'] ?? 0);
        $resolvedModel = $payload['model'] ?? $model;

        /*
         * اسم نموذج غير موجود لا يُرجع خطأ — يسقط بصمت إلى الافتراضي.
         * حدث هذا فعلًا: أصعب مرحلة ظلت تعمل على أضعف نموذج لأسابيع دون أثر.
         */
        if ($resolvedModel !== $model) {
            Log::warning('المزود استخدم نموذجًا غير المطلوب', [
                'requested' => $model,
                'resolved' => $resolvedModel,
                'stage' => $request->stage,
            ]);
        }

        return new AIResponse(
            content: (string) ($payload['choices'][0]['message']['content'] ?? ''),
            provider: $this->provider(),
            model: $resolvedModel,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
            costUsd: $this->cost($resolvedModel, $inputTokens, $outputTokens),
            stage: $request->stage,
            finishReason: $payload['choices'][0]['finish_reason'] ?? null,
        );
    }

    private function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = config("ai.pricing.{$model}") ?? config('ai.pricing.default');

        return round(
            ($inputTokens / 1_000_000) * (float) $pricing['input']
            + ($outputTokens / 1_000_000) * (float) $pricing['output'],
            6,
        );
    }

    private function wrap(ConnectionException|RequestException $exception): AIProviderException
    {
        $status = $exception instanceof RequestException ? $exception->response->status() : null;

        /*
         * السبب الحقيقي يبقى في السجل، ولا يظهر للمستخدم.
         * انقطاع الاتصال بلا حالة يعني في الغالب تجاوز المهلة — وهو ما يحدث
         * عندما يكون النموذج أبطأ من DEEPSEEK_TIMEOUT.
         */
        Log::warning('فشل استدعاء مزود الذكاء الاصطناعي', [
            'provider' => $this->provider(),
            'status' => $status,
            'kind' => $exception instanceof ConnectionException ? 'connection_or_timeout' : 'http_error',
            'timeout_seconds' => config("ai.{$this->profile}.timeout"),
        ]);

        // اسم المزوّد الفعلي لا اسم ثابت: السلسلة تحتاج أن تعرف مَن سقط،
        // وسجلٌّ يقول «DeepSeek» عن عطلٍ في Groq يضلّل من يقرأه.
        return new AIProviderException($this->provider(), $status);
    }
}
