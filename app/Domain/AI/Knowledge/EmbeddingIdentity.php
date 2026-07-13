<?php

namespace App\Domain\AI\Knowledge;

/**
 * هوية نموذج التضمين المعتمدة — مصدر واحد يقرّر أي نموذج يُخزَّن ويُقارن به.
 *
 * القاعدة: العامل الخاص (Ollama) إن كان مفعّلاً فهو المنتج والهوية هويته؛
 * وإلا فالمنتج هو عميل الـAPI المضمّن وهويته نموذج الـAPI. لا يجوز خلط متجهات
 * نموذجين تحت اسم واحد — cosine بين فضاءين مختلفين بلا معنى.
 */
class EmbeddingIdentity
{
    /** هل مسار الـAPI المضمّن هو المنتج النشط للمتجهات؟ */
    public static function inlineApiActive(): bool
    {
        return (bool) config('services.knowledge.embedding_api.enabled', true)
            && filled(config('services.knowledge.embedding_api.key'))
            && ! (bool) config('services.private_worker.enabled', false);
    }

    public static function modelName(): string
    {
        if (self::inlineApiActive()) {
            return (string) config('services.knowledge.embedding_api.model', 'baai/bge-m3');
        }

        return (string) config('services.knowledge.embedding_model', 'nomic-embed-text');
    }

    public static function modelVersion(): string
    {
        return (string) config('services.knowledge.embedding_model_version', 'v1');
    }
}
