<?php

namespace App\Contracts\AI;

use App\Support\AI\AIRequest;
use App\Support\AI\AIResponse;
use Closure;

interface ArtificialIntelligenceGateway
{
    /**
     * تشغيل طلب واحد وإرجاع النتيجة كاملة مع التكلفة والزمن.
     */
    public function run(AIRequest $request): AIResponse;

    /**
     * تشغيل متدفق: يُستدعى $onChunk لكل جزء نصي، وتُرجع النتيجة المجمعة.
     *
     * @param  Closure(string): void  $onChunk
     */
    public function stream(AIRequest $request, Closure $onChunk): AIResponse;

    /**
     * اسم المزود كما يُسجل في ai_usage_records.
     */
    public function provider(): string;

    /**
     * النموذج الفعلي المستخدم لمستوى معين قبل التشغيل، لعرض التكلفة المتوقعة.
     */
    public function modelForTier(string $tier): string;
}
