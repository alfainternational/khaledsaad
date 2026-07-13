<?php

namespace App\Console\Commands;

use App\Domain\AI\Services\PrivateWorkerAiGateway;
use Illuminate\Console\Command;

class PrivateWorkerGenerationCanaryCommand extends Command
{
    private const MARKER = 'LOCAL_REASONING_CANARY_20260713';

    protected $signature = 'private-worker:generation-canary {--json : Emit machine-readable output}';

    protected $description = 'Verify synchronous reasoning is completed by the private local model';

    public function handle(PrivateWorkerAiGateway $gateway): int
    {
        $started = microtime(true);
        $response = $gateway->generateText(
            'حلل العبارة التالية بإيجاز: انخفاض الاحتفاظ مع ارتفاع الزيارات. أعد JSON فقط بالمفاتيح canary وanalysis وrecommendation وmetric. اجعل canary مساوية تماماً لـ'.self::MARKER.'.',
            'أنت محلل محلي. استنتج سبباً محتملاً، توصية عملية، ومقياس تحقق. لا تستخدم الإنترنت ولا أي مزود خارجي.',
        );
        $decoded = is_string($response) ? json_decode($response, true) : null;
        $ok = is_array($decoded)
            && ($decoded['canary'] ?? null) === self::MARKER
            && is_string($decoded['analysis'] ?? null)
            && trim($decoded['analysis']) !== ''
            && is_string($decoded['_model_name'] ?? null)
            && trim($decoded['_model_name']) !== '';
        $payload = [
            'ok' => $ok,
            'model' => is_array($decoded) ? ($decoded['_model_name'] ?? null) : null,
            'latency_milliseconds' => (int) round((microtime(true) - $started) * 1000),
            'has_analysis' => is_array($decoded) && trim((string) ($decoded['analysis'] ?? '')) !== '',
            'has_recommendation' => is_array($decoded) && trim((string) ($decoded['recommendation'] ?? '')) !== '',
            'has_metric' => is_array($decoded) && trim((string) ($decoded['metric'] ?? '')) !== '',
        ];
        $this->line(json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($this->option('json') ? 0 : JSON_PRETTY_PRINT),
        ));

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
