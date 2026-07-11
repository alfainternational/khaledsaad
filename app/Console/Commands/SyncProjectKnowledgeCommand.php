<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\ProjectKnowledgeSnapshotBuilder;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\Project\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

class SyncProjectKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:sync-projects {--project=}';

    protected $description = 'Build versioned structured knowledge snapshots for projects';

    public function handle(ProjectKnowledgeSnapshotBuilder $builder, StructuredKnowledgeRepository $repository): int
    {
        try {
            return $this->sync($builder, $repository);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Project knowledge synchronization failed.');

            return self::FAILURE;
        }
    }

    private function sync(ProjectKnowledgeSnapshotBuilder $builder, StructuredKnowledgeRepository $repository): int
    {
        $projectOption = $this->option('project');
        if ($projectOption !== null && (! ctype_digit((string) $projectOption) || (int) $projectOption < 1)) {
            throw new UnexpectedValueException('The project option must be a positive integer.');
        }

        $synced = 0;
        $unchanged = 0;
        $failed = 0;

        Project::query()
            ->with('workspace:id,account_id')
            ->when($projectOption !== null, fn ($query) => $query->whereKey((int) $projectOption))
            ->chunkById(25, function ($projects) use ($builder, $repository, &$synced, &$unchanged, &$failed): void {
                foreach ($projects as $project) {
                    try {
                        if ($project->workspace === null) {
                            throw new UnexpectedValueException('Project workspace is missing.');
                        }

                        $snapshot = $builder->build($project);
                        $scope = KnowledgeScope::forProject(
                            accountId: (int) $project->workspace->account_id,
                            workspaceId: (int) $project->workspace_id,
                            projectId: (int) $project->id,
                        );
                        $uri = 'project://'.$project->public_id.'/snapshot';
                        $current = $repository->latestDocument($scope, 'project_snapshot', $uri);

                        $repository->storeDocument(
                            $scope,
                            'project_snapshot',
                            $uri,
                            $snapshot['title'],
                            $snapshot['content'],
                            $snapshot['chunks'],
                            100,
                        );

                        if ($current !== null && $current->content === $snapshot['content']) {
                            $unchanged++;
                        } else {
                            $synced++;
                        }
                    } catch (Throwable $exception) {
                        Log::error('Project knowledge synchronization failed.', [
                            'project_id' => $project->id,
                            'exception_type' => $exception::class,
                        ]);
                        $this->warn('Project '.$project->id.' could not be synchronized.');
                        $failed++;
                    }
                }
            });

        $this->line("Synced: {$synced}; unchanged: {$unchanged}; failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
