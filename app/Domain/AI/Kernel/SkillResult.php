<?php

namespace App\Domain\AI\Kernel;

/**
 * نتيجة موحّدة لأي مهارة. تحمل دليل المصدر (provenance): هل النتيجة من المحرك
 * المحلي (مجانية، ثابتة) أم صُقلت بـ LLM؟ هذا يجعل النظام شفافاً وقابلاً للتدهور
 * بأمان (graceful degradation): تبقى نتيجة محلية قوية حتى لو غاب أي مصدر خارجي.
 */
final class SkillResult
{
    public const SOURCE_LOCAL = 'local';
    public const SOURCE_LLM = 'llm';
    public const SOURCE_HYBRID = 'hybrid';
    public const SOURCE_NONE = 'none';

    /**
     * @param  array<int, string>  $bullets
     * @param  array<int, array{label: string, route?: string}>  $actions
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $code,
        public readonly string $headline,
        public readonly string $body = '',
        public readonly array $bullets = [],
        public readonly int $confidence = 0,
        public readonly string $source = self::SOURCE_LOCAL,
        public readonly array $actions = [],
        public readonly array $meta = [],
    ) {}

    public static function empty(string $code = 'none'): self
    {
        return new self(code: $code, headline: '', source: self::SOURCE_NONE);
    }

    /**
     * إعادة بناء النتيجة من مصفوفة (للكاش) — نخزّن مصفوفات لا كائنات لمتانة أعلى.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) ($data['code'] ?? 'none'),
            headline: (string) ($data['headline'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            bullets: (array) ($data['bullets'] ?? []),
            confidence: (int) ($data['confidence'] ?? 0),
            source: (string) ($data['source'] ?? self::SOURCE_NONE),
            actions: (array) ($data['actions'] ?? []),
            meta: (array) ($data['meta'] ?? []),
        );
    }

    public function isEmpty(): bool
    {
        return $this->source === self::SOURCE_NONE && $this->headline === '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'headline' => $this->headline,
            'body' => $this->body,
            'bullets' => $this->bullets,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'actions' => $this->actions,
            'meta' => $this->meta,
        ];
    }
}
