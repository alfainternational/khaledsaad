<?php

namespace App\Services\Content;

use App\Models\AiUsageRecord;
use App\Models\Content;
use App\Models\LearningContentUpdateDraft;
use App\Models\User;
use App\Modules\Learning\MarketingCourseCatalog;
use App\Modules\Measurement\QueryBudgetManager;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use InvalidArgumentException;
use Throwable;

class MarketingLessonUpdateDraftService
{
    public function __construct(
        private readonly StructuredRunner $runner,
        private readonly QueryBudgetManager $budgets,
        private readonly MarketingCourseCatalog $catalog,
    ) {}

    /** @param array<int, string> $sources */
    public function create(Content $content, User $admin, array $sources, ?string $notes = null): LearningContentUpdateDraft
    {
        if (! str_starts_with((string) $content->source_key, 'marketing-course-') || $content->learning_order === null) {
            throw new InvalidArgumentException('Only marketing-course lessons can be refreshed.');
        }

        $workspace = $admin->primaryWorkspace();
        $reservation = $this->budgets->reserve($workspace, 1, 'marketing_lesson_update');
        $costFloor = (int) AiUsageRecord::max('id');
        $lesson = collect($this->catalog->lessons())->firstWhere('number', $content->learning_order);
        $context = [
            'title' => $content->title,
            'current_body_html' => $content->body_html,
            'outline' => $content->learning_meta['outline'] ?? [],
            'linked_exercises' => $lesson['exercises'] ?? [],
            'trusted_update_sources' => $sources,
            'editor_notes' => $notes,
            'rule' => 'حافظ على هدف الدرس وتطبيقاته. لا تضف ادعاء غير مسنود، ولا تنشر شيئًا؛ المطلوب مسودة تحريرية فقط.',
        ];

        try {
            $payload = $this->runner->run(AIRequest::json(
                messages: [
                    ['role' => 'system', 'content' => 'حدّث درسًا تسويقيًا عربيًا بناءً على المصادر الموثوقة المرفقة. أعد مسودة كاملة قابلة للمراجعة، وحافظ على RTL وبنية HTML الآمنة. لا تعتبر المسودة منشورة.'],
                    ['role' => 'user', 'content' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)],
                ],
                schema: $this->schema(),
                tier: 'standard',
                stage: 'marketing_lesson_update',
            ));
            $this->budgets->settle($reservation, $this->costSince($costFloor));
        } catch (Throwable $exception) {
            $this->budgets->release($reservation, $this->costSince($costFloor));
            throw $exception;
        }

        return $content->learningUpdateDrafts()->create([
            'requested_by' => $admin->id,
            'status' => 'draft',
            'context_hash' => hash('sha256', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'summary' => $payload['summary'],
            'proposed_body_html' => $payload['proposed_body_html'],
            'changes' => $payload['changes'],
            'sources' => $payload['sources_used'],
            'generated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['summary', 'proposed_body_html', 'changes', 'sources_used'],
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string', 'minLength' => 20],
                'proposed_body_html' => ['type' => 'string', 'minLength' => 50],
                'changes' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                'sources_used' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
            ],
        ];
    }

    private function costSince(int $floorId): float
    {
        return (float) AiUsageRecord::query()->where('id', '>', $floorId)
            ->where('stage', 'marketing_lesson_update')->sum('cost_usd');
    }
}
