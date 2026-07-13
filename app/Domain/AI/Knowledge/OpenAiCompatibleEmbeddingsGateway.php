<?php

namespace App\Domain\AI\Knowledge;

use App\Contracts\EmbeddingsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * عميل تضمينات HTTP متوافق مع OpenAI (/v1/embeddings) — يعمل من الخادم مباشرة
 * بلا عامل خارجي. الافتراضي NVIDIA NIM بنموذج bge-m3 متعدد اللغات (عربية ممتازة)
 * بنفس مفتاح NVIDIA المستخدم للتوليد. يقرأ الإعداد وقت النداء ليلتقط تجاوزات
 * الآدمن (SettingsStore) فوراً، ويتدهور بأمان (null) عند الفشل.
 */
class OpenAiCompatibleEmbeddingsGateway implements EmbeddingsGateway
{
    public function enabled(): bool
    {
        return (bool) config('services.knowledge.embedding_api.enabled', true)
            && filled(config('services.knowledge.embedding_api.key'));
    }

    public function model(): string
    {
        return (string) config('services.knowledge.embedding_api.model', 'baai/bge-m3');
    }

    public function embed(array $texts, string $inputType = 'passage'): ?array
    {
        $texts = array_values(array_filter($texts, fn ($t): bool => is_string($t) && trim($t) !== ''));
        if (! $this->enabled() || $texts === []) {
            return null;
        }

        $base = rtrim((string) config('services.knowledge.embedding_api.base_url', 'https://integrate.api.nvidia.com/v1'), '/');

        $payload = [
            'model' => $this->model(),
            'input' => $texts,
            'encoding_format' => 'float',
        ];
        // NIM يتطلب نوع الإدخال لنماذج الاسترجاع غير المتماثلة؛ OpenAI الأصلي يرفضه.
        if ((bool) config('services.knowledge.embedding_api.send_input_type', true)) {
            $payload['input_type'] = in_array($inputType, ['query', 'passage'], true) ? $inputType : 'passage';
        }

        try {
            $response = Http::withToken((string) config('services.knowledge.embedding_api.key'))
                ->connectTimeout((int) config('services.knowledge.embedding_api.connect_timeout', 10))
                ->timeout((int) config('services.knowledge.embedding_api.timeout', 30))
                ->acceptJson()
                ->post("{$base}/embeddings", $payload);

            if (! $response->successful()) {
                Log::warning('Embeddings gateway API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 300),
                ]);

                return null;
            }

            $rows = $response->json('data');
            if (! is_array($rows) || count($rows) !== count($texts)) {
                return null;
            }

            usort($rows, fn (array $a, array $b): int => ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0)));

            $vectors = [];
            foreach ($rows as $row) {
                $vector = $row['embedding'] ?? null;
                if (! is_array($vector) || $vector === []) {
                    return null;
                }
                $vectors[] = array_map(static fn ($v): float => (float) $v, $vector);
            }

            return $vectors;
        } catch (\Throwable $e) {
            Log::warning('Embeddings gateway connection error: '.$e->getMessage());

            return null;
        }
    }
}
