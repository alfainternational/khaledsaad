<?php

namespace App\Support\AI;

use InvalidArgumentException;

/**
 * وصف كامل لطلب واحد نحو مزود الذكاء الاصطناعي.
 *
 * سبب وجوده: خط أنابيب التقارير يحتاج تمرير مخطط JSON، ومستوى النموذج،
 * وسياق التشغيل لتسجيل التكلفة. تمرير هذه القيم كوسائط منفصلة يجعل كل
 * توسعة مستقبلية كسرًا لكل مستدعٍ.
 */
final class AIRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $jsonSchema  مخطط JSON المطلوب فرضه على المخرج.
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $model = null,
        public readonly string $tier = 'economy',
        public readonly bool $expectsJson = false,
        public readonly ?array $jsonSchema = null,
        public readonly float $temperature = 0.2,
        public readonly ?int $maxTokens = null,
        public readonly ?string $stage = null,
        // إنقاذ: حين يفشل التحقق نهائيًا، احتفظ بعناصر المصفوفة الصالحة بدل
        // إسقاط المخرج كله. مفيد للخلاصة: نبقي النتائج السليمة ونتجاهل المعطوبة.
        public readonly bool $salvage = false,
        /*
         * لغة المخرَج. `null` تعني لغة الطلب الحالية — وهو ما نريده في كل
         * استدعاء يقرأ مخرجه إنسان. تُمرَّر صراحةً حين يُنفَّذ الاستدعاء
         * خارج دورة الطلب (مهمة في طابور تحمل لغة صاحبها).
         */
        public readonly ?string $outputLocale = null,
        /*
         * استدعاء لا يُملى عليه لغة إطلاقًا.
         *
         * موضعان لا ثالث لهما: المترجم — يحدّد لغته بنفسه وحقنُ توجيهٍ
         * فيه يجعله يترجم إلى لغة الواجهة لا إلى اللغة المطلوبة؛ وأي
         * استعلام قياس، لأن تغيير لغة السؤال يقيس سؤالًا آخر (§٤.٢).
         */
        public readonly bool $localeNeutral = false,
    ) {
        if ($messages === []) {
            throw new InvalidArgumentException('طلب الذكاء الاصطناعي يحتاج رسالة واحدة على الأقل.');
        }

        foreach ($messages as $message) {
            if (! isset($message['role'], $message['content'])) {
                throw new InvalidArgumentException('كل رسالة يجب أن تحتوي على role وcontent.');
            }
        }

        if ($jsonSchema !== null && ! $expectsJson) {
            throw new InvalidArgumentException('لا يمكن تمرير مخطط JSON دون تفعيل expectsJson.');
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $schema
     */
    public static function json(
        array $messages,
        array $schema,
        string $tier = 'standard',
        ?string $stage = null,
        bool $salvage = false,
        ?string $outputLocale = null,
        bool $localeNeutral = false,
    ): self {
        return new self(
            messages: $messages,
            tier: $tier,
            expectsJson: true,
            jsonSchema: $schema,
            stage: $stage,
            salvage: $salvage,
            outputLocale: $outputLocale,
            localeNeutral: $localeNeutral,
        );
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public static function text(
        array $messages,
        string $tier = 'economy',
        ?string $stage = null,
        ?string $outputLocale = null,
        bool $localeNeutral = false,
    ): self {
        return new self(
            messages: $messages,
            tier: $tier,
            stage: $stage,
            outputLocale: $outputLocale,
            localeNeutral: $localeNeutral,
        );
    }

    public function withMessages(array $messages): self
    {
        return new self(
            messages: $messages,
            model: $this->model,
            tier: $this->tier,
            expectsJson: $this->expectsJson,
            jsonSchema: $this->jsonSchema,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            stage: $this->stage,
            salvage: $this->salvage,
            outputLocale: $this->outputLocale,
            localeNeutral: $this->localeNeutral,
        );
    }
}
