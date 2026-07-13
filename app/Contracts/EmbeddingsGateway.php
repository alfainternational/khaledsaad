<?php

namespace App\Contracts;

/**
 * عقد مزوّد التضمينات (embeddings): يحوّل نصوصاً إلى متجهات دلالية.
 *
 * يكمل AiGatewayInterface (المخصص لتوليد النص): هذا العقد للفهم لا للكتابة.
 * التطبيق الافتراضي HTTP متوافق مع OpenAI /v1/embeddings (NVIDIA NIM وأخواتها)،
 * ويتدهور بأمان (null) عند غياب المفتاح — فيبقى النظام على المطابقة المعجمية.
 */
interface EmbeddingsGateway
{
    /** هل المزوّد مهيأ (مفعّل وبمفتاح)؟ */
    public function enabled(): bool;

    /** اسم النموذج الذي يولّد به المتجهات (هوية تخزين المتجه). */
    public function model(): string;

    /**
     * تضمين دفعة نصوص. النوع query للاستعلامات وpassage للمحتوى المخزّن.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>|null  متجه لكل نص بنفس الترتيب، أو null عند الفشل.
     */
    public function embed(array $texts, string $inputType = 'passage'): ?array;
}
