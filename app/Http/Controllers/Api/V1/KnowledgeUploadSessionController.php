<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\Models\KnowledgeUploadSession;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use App\Domain\AI\Knowledge\Uploads\TextKnowledgeExtractor;
use App\Domain\AI\Worker\KnowledgeUploadJobDispatcher;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\KnowledgeUploadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KnowledgeUploadSessionController extends Controller
{
    use ResolvesCurrentProject;

    private const EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm', 'pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff'];

    public function store(Request $request): JsonResponse
    {
        $this->enabled();
        $project = $this->currentProject()->loadMissing('workspace');
        $this->authorize('update', $project);
        $maxBytes = (int) config('services.knowledge.chunked_max_bytes', 52428800);
        $data = $request->validate([
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:120'],
            'byte_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $extension = strtolower(pathinfo(basename($data['original_name']), PATHINFO_EXTENSION));
        validator(['extension' => $extension], ['extension' => ['required', Rule::in(self::EXTENSIONS)]])->validate();
        $chunkSize = (int) config('services.knowledge.chunk_bytes', 1048576);
        $publicId = 'ups_'.Str::lower((string) Str::ulid());
        $path = 'knowledge-upload-sessions/'.$project->workspace->account_id.'/'.$project->workspace_id.'/'.$project->id.'/'.$publicId;
        $session = KnowledgeUploadSession::query()->create([
            'public_id' => $publicId,
            'account_id' => $project->workspace->account_id,
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'uploaded_by_user_id' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename($data['original_name']),
            'mime_type' => $data['mime_type'],
            'extension' => $extension,
            'byte_size' => $data['byte_size'],
            'chunk_size' => $chunkSize,
            'chunk_count' => (int) ceil($data['byte_size'] / $chunkSize),
            'sha256' => $data['sha256'],
            'expires_at' => now()->addMinutes((int) config('services.knowledge.chunk_session_ttl_minutes', 120)),
        ]);

        return response()->json(['data' => $this->sessionData($session)], 201);
    }

    public function chunk(Request $request): Response
    {
        $this->enabled();
        $session = $this->session((string) $request->route('sessionPublicId'));
        abort_if($session->status !== 'open' || $session->expires_at->isPast(), 409);
        $index = (int) $request->route('index');
        abort_if($index < 0 || $index >= $session->chunk_count, 422);
        $content = $request->getContent();
        $expected = $index === $session->chunk_count - 1
            ? $session->byte_size - ($index * $session->chunk_size)
            : $session->chunk_size;
        abort_if(strlen($content) !== $expected, 422, 'Chunk size does not match the upload contract.');
        Storage::disk($session->disk)->put($session->path.'/chunks/'.$index.'.part', $content);

        return response()->noContent();
    }

    public function complete(
        Request $request,
        KnowledgeUploadIndexer $indexer,
        KnowledgeUploadJobDispatcher $dispatcher,
        TextKnowledgeExtractor $extractor,
    ): JsonResponse {
        $this->enabled();
        $session = $this->session((string) $request->route('sessionPublicId'));
        abort_if($session->status !== 'open' || $session->expires_at->isPast(), 409);
        $disk = Storage::disk($session->disk);
        $finalPath = 'knowledge-uploads/'.$session->account_id.'/'.$session->workspace_id.'/'.$session->project_id.'/'.$session->sha256.'.'.$session->extension;
        $disk->makeDirectory(dirname($finalPath));
        $target = fopen($disk->path($finalPath), 'wb');
        abort_if($target === false, 500);
        try {
            for ($index = 0; $index < $session->chunk_count; $index++) {
                $part = $session->path.'/chunks/'.$index.'.part';
                abort_unless($disk->exists($part), 422, 'Upload is incomplete.');
                $source = fopen($disk->path($part), 'rb');
                abort_if($source === false, 500);
                stream_copy_to_stream($source, $target);
                fclose($source);
            }
        } finally {
            fclose($target);
        }
        if (filesize($disk->path($finalPath)) !== $session->byte_size || hash_file('sha256', $disk->path($finalPath)) !== $session->sha256) {
            $disk->delete($finalPath);
            abort(422, 'Assembled upload failed integrity verification.');
        }
        $upload = KnowledgeUpload::query()->firstOrCreate(
            ['project_id' => $session->project_id, 'sha256' => $session->sha256],
            [
                'public_id' => 'upl_'.Str::lower((string) Str::ulid()),
                'account_id' => $session->account_id,
                'workspace_id' => $session->workspace_id,
                'uploaded_by_user_id' => $session->uploaded_by_user_id,
                'disk' => $session->disk,
                'path' => $finalPath,
                'original_name' => $session->original_name,
                'mime_type' => $session->mime_type,
                'extension' => $session->extension,
                'byte_size' => $session->byte_size,
                'status' => 'stored',
            ],
        );
        $session->update(['status' => 'completed']);
        $disk->deleteDirectory($session->path);
        if ($extractor->supports($upload->mime_type)) {
            $upload = $indexer->index($upload);
            $status = 201;
        } else {
            $dispatcher->dispatch($upload);
            $upload->refresh();
            $status = 202;
        }

        return (new KnowledgeUploadResource($upload))->response()->setStatusCode($status);
    }

    private function session(string $publicId): KnowledgeUploadSession
    {
        $project = $this->currentProject();
        $this->authorize('update', $project);

        return KnowledgeUploadSession::query()->where('project_id', $project->id)->where('public_id', $publicId)->firstOrFail();
    }

    private function enabled(): void
    {
        abort_unless((bool) config('services.knowledge.chunked_uploads', false), 404);
    }

    /** @return array<string, mixed> */
    private function sessionData(KnowledgeUploadSession $session): array
    {
        return [
            'public_id' => $session->public_id,
            'chunk_size' => $session->chunk_size,
            'chunk_count' => $session->chunk_count,
            'expires_at' => $session->expires_at?->toIso8601String(),
        ];
    }
}
