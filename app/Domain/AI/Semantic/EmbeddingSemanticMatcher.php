<?php

namespace App\Domain\AI\Semantic;

use App\Contracts\EmbeddingsGateway;
use App\Domain\AI\Knowledge\VectorMath;
use Illuminate\Support\Facades\Cache;

/**
 * فهم دلالي بالتضمينات العصبية فوق الأساس المعجمي — الترقية الموعودة في عقد
 * SemanticMatcher دون لمس أي مستهلك.
 *
 * المنهج: التشابه/القوة = الأفضل بين القياس المعجمي (يعمل دائماً، صفر تكلفة)
 * وجيب التمام بين متجهي النصين (يفهم المعنى لا الألفاظ). عند غياب المزوّد أو
 * فشله يتصرف كمعجمي خالص — لا انكسار أبداً.
 *
 * الأداء: متجه كل نص يُخزَّن في الكاش ببصمة محتواه (النصوص الثابتة مثل مفاهيم
 * المعجم وذخيرة المعرفة تُضمَّن مرة واحدة)، وwarm() تضمّن دفعة كاملة بنداء واحد.
 */
class EmbeddingSemanticMatcher implements SemanticMatcher
{
    /** عتبة اعتبار المفهوم «معبَّراً عنه» (نفس عقد المعجمي). */
    private const EXPRESS_THRESHOLD = 0.5;

    /** معايرة جيب التمام إلى 0..1: تحت الأدنى ضجيج، فوق الأعلى تطابق صريح. */
    private const COSINE_FLOOR = 0.35;

    private const COSINE_CEIL = 0.82;

    /** كاش داخل الطلب لتفادي قراءة الكاش الخارجي مراراً لنفس النص. */
    private array $memo = [];

    public function __construct(
        private readonly EmbeddingsGateway $embeddings,
        private readonly LexicalSemanticMatcher $lexical,
        private readonly VectorMath $vectorMath,
        private readonly ConceptLexicon $lexicon,
    ) {}

    public function expresses(string $text, string $conceptKey): bool
    {
        return $this->strength($text, $conceptKey) >= self::EXPRESS_THRESHOLD;
    }

    public function strength(string $text, string $conceptKey): float
    {
        $lexicalStrength = $this->lexical->strength($text, $conceptKey);
        if ($lexicalStrength >= 1.0 || ! $this->embeddings->enabled()) {
            return $lexicalStrength;
        }

        $conceptText = $this->conceptText($conceptKey);
        if ($conceptText === '' || trim($text) === '') {
            return $lexicalStrength;
        }

        $semantic = $this->cosineSimilarity($text, $conceptText);

        return $semantic === null ? $lexicalStrength : max($lexicalStrength, $semantic);
    }

    public function similarity(string $textA, string $textB): float
    {
        $lexicalSimilarity = $this->lexical->similarity($textA, $textB);
        if (! $this->embeddings->enabled() || trim($textA) === '' || trim($textB) === '') {
            return $lexicalSimilarity;
        }

        $semantic = $this->cosineSimilarity($textA, $textB);

        return $semantic === null ? $lexicalSimilarity : max($lexicalSimilarity, $semantic);
    }

    /**
     * تسخين الكاش لدفعة نصوص بنداء API واحد — تستدعيها المستهلكات التي تقارن
     * استعلاماً بذخيرة كبيرة (قاعدة المعرفة التسويقية) قبل حلقة similarity().
     *
     * @param  list<string>  $texts
     */
    public function warm(array $texts): void
    {
        if (! $this->embeddings->enabled()) {
            return;
        }

        $missing = [];
        foreach (array_unique(array_filter($texts, fn ($t): bool => is_string($t) && trim($t) !== '')) as $text) {
            $key = $this->cacheKey($text);
            if (! isset($this->memo[$key]) && Cache::get($key) === null) {
                $missing[$key] = $text;
            }
        }
        if ($missing === []) {
            return;
        }

        $batchSize = max(1, min(64, (int) config('services.knowledge.embedding_api.batch', 32)));
        foreach (array_chunk($missing, $batchSize, true) as $batch) {
            $vectors = $this->embeddings->embed(array_values($batch), 'passage');
            if (! is_array($vectors) || count($vectors) !== count($batch)) {
                continue;
            }
            $index = 0;
            foreach (array_keys($batch) as $key) {
                $this->storeVector($key, $vectors[$index]);
                $index++;
            }
        }
    }

    /** جيب تمام معاير 0..1 بين نصين، أو null عند تعذر التضمين. */
    private function cosineSimilarity(string $textA, string $textB): ?float
    {
        $vectorA = $this->vectorFor($textA);
        $vectorB = $this->vectorFor($textB);
        if ($vectorA === null || $vectorB === null) {
            return null;
        }

        try {
            $cosine = $this->vectorMath->cosine($vectorA, $vectorB);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $scaled = (max(self::COSINE_FLOOR, min(self::COSINE_CEIL, $cosine)) - self::COSINE_FLOOR)
            / (self::COSINE_CEIL - self::COSINE_FLOOR);

        return round($scaled, 4);
    }

    /** @return list<float>|null */
    private function vectorFor(string $text): ?array
    {
        $key = $this->cacheKey($text);
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $this->memo[$key] = $cached;
        }

        $vectors = $this->embeddings->embed([$text], 'passage');
        $vector = $vectors[0] ?? null;
        if (! is_array($vector)) {
            return null;
        }

        return $this->storeVector($key, $vector);
    }

    /** @param  list<float>  $vector
     * @return list<float>|null */
    private function storeVector(string $key, array $vector): ?array
    {
        try {
            $normalized = $this->vectorMath->normalize($vector);
        } catch (\InvalidArgumentException) {
            return null;
        }

        Cache::put($key, $normalized, now()->addDays(14));

        return $this->memo[$key] = $normalized;
    }

    private function cacheKey(string $text): string
    {
        return 'semvec:'.md5($this->embeddings->model()).':'.hash('sha256', trim($text));
    }

    /** نص المفهوم من المعجم (عباراته ومصطلحاته) — يُضمَّن مرة ويُكاش طويلاً. */
    private function conceptText(string $conceptKey): string
    {
        $concept = $this->lexicon->concept($conceptKey);
        if ($concept === null) {
            return '';
        }

        return trim(implode('، ', array_filter(array_merge(
            (array) ($concept['phrases'] ?? []),
            (array) ($concept['terms'] ?? []),
        ))));
    }
}
