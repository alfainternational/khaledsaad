<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Knowledge\Uploads\KnowledgeExtractionException;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Api\V1\Concerns\ResolvesCurrentProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreKnowledgeUploadRequest;
use App\Http\Resources\V1\KnowledgeUploadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KnowledgeUploadController extends Controller
{
    use ResolvesCurrentProject;

    private const EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $project = $this->currentProject();
        $this->authorize('view', $project);

        return KnowledgeUploadResource::collection(
            KnowledgeUpload::query()
                ->where('project_id', $project->id)
                ->latest('id')
                ->limit(100)
                ->get(),
        );
    }

    public function store(
        StoreKnowledgeUploadRequest $request,
        KnowledgeUploadIndexer $indexer,
    ): JsonResponse {
        $project = $this->currentProject()->loadMissing('workspace');
        $this->authorize('update', $project);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => ['امتداد الملف غير مدعوم لاستخراج المعرفة النصية.'],
            ]);
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $existing = KnowledgeUpload::query()
            ->where('project_id', $project->id)
            ->where('sha256', $sha256)
            ->first();
        if ($existing) {
            return (new KnowledgeUploadResource($existing))->response();
        }

        $path = implode('/', [
            'knowledge-uploads',
            $project->workspace->account_id,
            $project->workspace_id,
            $project->id,
            $sha256.'.'.$extension,
        ]);
        Storage::disk('local')->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        try {
            $upload = KnowledgeUpload::query()->create([
                'public_id' => 'upl_'.Str::lower((string) Str::ulid()),
                'account_id' => $project->workspace->account_id,
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'uploaded_by_user_id' => $request->user()->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => (string) $file->getMimeType(),
                'extension' => $extension,
                'byte_size' => (int) $file->getSize(),
                'sha256' => $sha256,
                'status' => 'stored',
            ]);
            $upload = $indexer->index($upload);
        } catch (KnowledgeExtractionException $exception) {
            return $this->extractionFailure($upload, $exception);
        } catch (\Throwable $exception) {
            if (! isset($upload)) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return (new KnowledgeUploadResource($upload))
            ->response()
            ->setStatusCode(201);
    }

    public function retry(Request $request, KnowledgeUploadIndexer $indexer): KnowledgeUploadResource|JsonResponse
    {
        $project = $this->currentProject();
        $this->authorize('update', $project);
        $upload = $this->upload($project, (string) $request->route('uploadPublicId'));

        try {
            return new KnowledgeUploadResource($indexer->index($upload));
        } catch (KnowledgeExtractionException $exception) {
            return $this->extractionFailure($upload->fresh(), $exception);
        }
    }

    public function destroy(
        Request $request,
        StructuredKnowledgeRepository $repository,
    ): Response {
        $project = $this->currentProject()->loadMissing('workspace');
        $this->authorize('update', $project);
        $upload = $this->upload($project, (string) $request->route('uploadPublicId'));

        $repository->deactivateDocuments(
            KnowledgeScope::forProject(
                (int) $project->workspace->account_id,
                (int) $project->workspace_id,
                (int) $project->id,
            ),
            'uploaded_file',
            'upload://'.$upload->public_id,
        );
        Storage::disk($upload->disk)->delete($upload->path);
        $upload->delete();

        return response()->noContent();
    }

    private function upload(Project $project, string $publicId): KnowledgeUpload
    {
        return KnowledgeUpload::query()
            ->where('project_id', $project->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function extractionFailure(KnowledgeUpload $upload, KnowledgeExtractionException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'تعذر استخراج نص صالح من الملف.',
            'code' => 'KNOWLEDGE_EXTRACTION_FAILED',
            'errors' => ['file' => [$exception->machineCode]],
            'data' => (new KnowledgeUploadResource($upload->fresh()))->resolve(),
        ], 422);
    }
}
