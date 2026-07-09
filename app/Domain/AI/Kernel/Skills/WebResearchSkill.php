<?php

namespace App\Domain\AI\Kernel\Skills;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\SkillResult;
use App\Domain\AI\Web\WebResearchService;

/**
 * مهارة البحث الحيّ في الإنترنت + التحليل والتصنيف اللحظي.
 *
 * تُستدعى بـ intent='web_research' مع signals['query']. real-time عند طلب
 * المستخدم. تنمّي معرفة النظام تلقائياً عبر WebResearchService.
 */
class WebResearchSkill implements Skill
{
    public function __construct(private readonly WebResearchService $research) {}

    public function code(): string
    {
        return 'web_research';
    }

    public function handles(AgentContext $context): bool
    {
        return $context->intent === 'web_research'
            && is_string($context->signal('query'))
            && trim((string) $context->signal('query')) !== '';
    }

    public function run(AgentContext $context): SkillResult
    {
        $query = (string) $context->signal('query');
        $data = $this->research->research($query, (int) ($context->signal('depth') ?? 3));

        $bullets = [];
        foreach (array_slice((array) ($data['findings'] ?? []), 0, 5) as $f) {
            $bullets[] = sprintf('[%s] %s', $f['category'] ?? 'عام', (string) ($f['title'] ?? ''));
        }

        return new SkillResult(
            code: $this->code(),
            headline: (string) ($data['summary'] ?? 'نتائج البحث'),
            body: '',
            bullets: $bullets,
            confidence: empty($data['findings']) ? 0 : 75,
            source: SkillResult::SOURCE_LOCAL,
            actions: [],
            meta: [
                'query' => $data['query'] ?? $query,
                'categories' => $data['categories'] ?? [],
                'findings' => $data['findings'] ?? [],
            ],
        );
    }
}
