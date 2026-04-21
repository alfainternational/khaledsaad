<?php

namespace App\Jobs;

use App\Application\Integration\CloudIntegrationService;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use Throwable;

/**
 * تنفيذ طلب تكامل سحابي بشكل غير متزامن (مثلاً بعد حفظ بيانات أو من جدولة).
 */
class CloudHttpRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90];

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $workspaceId,
        public string $method,
        public string $path,
        public array $query = [],
        public array $payload = [],
        public ?int $actorUserId = null,
    ) {
        $this->onQueue('integrations');
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CloudIntegrationService $cloud): array
    {
        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        $user = $this->actorUserId !== null
            ? User::query()->find($this->actorUserId)
            : null;

        return match (strtoupper($this->method)) {
            'GET' => $cloud->get($workspace, $user, $this->path, $this->query),
            'POST' => $cloud->post($workspace, $user, $this->path, $this->payload),
            default => throw new InvalidArgumentException('Unsupported cloud HTTP method: '.$this->method),
        };
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['cloud-integration', 'workspace:'.$this->workspaceId];
    }
}
