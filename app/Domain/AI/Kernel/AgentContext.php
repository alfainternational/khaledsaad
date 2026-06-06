<?php

namespace App\Domain\AI\Kernel;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;

/**
 * السياق الذي "يستيقظ" داخله الوكيل في كل طلب HTTP عادي.
 *
 * كائن غير قابل للتغيير (immutable) يحمل كل ما يحتاجه العقل ليفكّر مرة واحدة
 * ثم يموت مع انتهاء الطلب — لا حالة مقيمة، لا ذاكرة RAM دائمة، لا daemon.
 * هذا جوهر العمل على استضافة مشتركة بموارد عادية.
 */
final class AgentContext
{
    /**
     * @param  array<string, mixed>  $signals  إشارات اللحظة (الصفحة، الأداة، آخر فعل...)
     * @param  array<int, array<string, mixed>>  $memories  ذاكرة ذات صلة استُرجعت لحظياً
     */
    public function __construct(
        public readonly string $intent,
        public readonly ?Workspace $workspace = null,
        public readonly ?Project $project = null,
        public readonly ?int $userId = null,
        public readonly array $signals = [],
        public readonly array $memories = [],
    ) {}

    public function withMemories(array $memories): self
    {
        return new self(
            intent: $this->intent,
            workspace: $this->workspace,
            project: $this->project,
            userId: $this->userId,
            signals: $this->signals,
            memories: $memories,
        );
    }

    public function signal(string $key, mixed $default = null): mixed
    {
        return $this->signals[$key] ?? $default;
    }
}
