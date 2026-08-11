<?php

namespace App\Modules\AiReadiness;

use App\Contracts\AI\ArtificialIntelligenceGateway;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Support\AI\AIRequest;

/**
 * محرك الإجابات فوق بوابة الذكاء القائمة.
 *
 * لا مزوّد HTTP ثانٍ: البوابة تتولّى بالفعل المهلات وإعادة المحاولة وتسجيل
 * التكلفة في `ai_usage_records` (§٤.١). بناء عميل موازٍ كان سيكرّر ذلك كله
 * ويخلق مسارًا لا يُسجَّل استهلاكه.
 *
 * الفارق الجوهري عن بقية استعمالات البوابة: **حرارة عالية وبلا مخطط**. غرض
 * الاستطلاع محاكاة ما يراه مشترٍ حقيقي حين يسأل، لا استخراج بيانات منظَّمة.
 * حرارة منخفضة تعطي الجواب نفسه في كل محاولة، فيصير `consistency` واحدًا
 * دائمًا — وهو مقياس التذبذب نفسه.
 */
class GatewayAnswerEngine implements AnswerEngine
{
    /**
     * حرارة الاستطلاع.
     *
     * قريبة من الافتراضي الذي يستعمله مستخدم عادي. رفعها فوق ذلك يقيس
     * عشوائية النموذج لا حال السوق.
     */
    private const TEMPERATURE = 0.9;

    public function __construct(private readonly ArtificialIntelligenceGateway $gateway) {}

    /**
     * @return array{text: string, latency_ms: int, cost_usd: float}
     */
    public function ask(string $question, string $locale = 'ar'): array
    {
        $response = $this->gateway->run(new AIRequest(
            messages: [
                /*
                 * بلا تعليمات تُوجّه الجواب نحو أسماء أو ضدّها: أي توجيه يجعلنا
                 * نقيس استجابة النموذج لتعليماتنا لا ما يراه المشتري.
                 */
                ['role' => 'user', 'content' => $question],
            ],
            tier: 'economy',
            temperature: self::TEMPERATURE,
            stage: 'answer_presence',
            /*
             * القياس لا لغة له تُملى عليه. السؤال يُطرح بلسان مشترٍ عربي
             * لأنه يقيس الظهور في السوق العربي؛ ترجمته إلى لغة الواجهة
             * تقيس سؤالًا آخر وتخالف §٤.٢. مُعلَن هنا رغم أن هذا المسار
             * يتجاوز `StructuredRunner` أصلًا — كي لا يصير نقلُه إليه
             * لاحقًا كسرًا صامتًا للقياس.
             */
            localeNeutral: true,
        ));

        return [
            'text' => $response->content,
            'latency_ms' => $response->latencyMs,
            'cost_usd' => $response->costUsd,
        ];
    }

    public function name(): string
    {
        return $this->gateway->provider();
    }

    public function model(): string
    {
        return $this->gateway->modelForTier('economy');
    }
}
