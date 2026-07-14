<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\Models\KnowledgeUpload;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use App\Domain\AI\Knowledge\Uploads\KnowledgeExtractionException;
use App\Domain\AI\Knowledge\Uploads\KnowledgeUploadIndexer;
use App\Domain\AI\Knowledge\Uploads\TextKnowledgeExtractor;
use App\Domain\AI\Worker\KnowledgeUploadJobDispatcher;
use App\Domain\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Http\Requests\Api\V1\StoreKnowledgeUploadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeUploadController extends Controller
{
    use InteractsWithWorkspaceContext;

    /** الامتدادات المدعومة لاستخراج المعرفة النصية — نفس مجموعة الـ API. */
    private const EXTENSIONS = [
        'txt', 'md', 'markdown', 'csv', 'json', 'html', 'htm',
        'pdf', 'docx', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff',
    ];

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $uploads = KnowledgeUpload::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->limit(100)
            ->get();

        return view('app.knowledge.index', [
            'workspace' => $this->currentWorkspace($request),
            'project' => $project,
            'uploads' => $uploads,
            'maxBytes' => (int) config('services.knowledge.upload_max_bytes', 8388608),
            'extensions' => self::EXTENSIONS,
        ]);
    }

    public function store(
        StoreKnowledgeUploadRequest $request,
        Project $project,
        KnowledgeUploadIndexer $indexer,
        KnowledgeUploadJobDispatcher $dispatcher,
        TextKnowledgeExtractor $extractor,
    ): RedirectResponse {
        $this->authorize('update', $project);
        $project->loadMissing('workspace');

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            return back()->withErrors(['file' => 'امتداد الملف غير مدعوم لاستخراج المعرفة النصية.']);
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        $existing = KnowledgeUpload::query()
            ->where('project_id', $project->id)
            ->where('sha256', $sha256)
            ->first();
        if ($existing) {
            return back()->with('status', 'هذا الملف مرفوع مسبقاً لهذا المشروع.');
        }

        $path = implode('/', [
            'knowledge-uploads',
            $project->workspace->account_id,
            $project->workspace_id,
            $project->id,
            $sha256.'.'.$extension,
        ]);
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

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

            if (! $extractor->supports($upload->mime_type)) {
                $dispatcher->dispatch($upload);

                return back()->with('status', 'تم رفع الملف وجُدولت معالجته لاستخراج المعرفة.');
            }

            $indexer->index($upload);
        } catch (KnowledgeExtractionException $exception) {
            return back()->with('error', 'تعذر استخراج نص صالح من الملف.');
        } catch (\Throwable $exception) {
            if (! isset($upload)) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'تم رفع الملف واستخراج معرفته بنجاح.');
    }

    public function retry(
        Request $request,
        Project $project,
        KnowledgeUpload $upload,
        KnowledgeUploadIndexer $indexer,
        KnowledgeUploadJobDispatcher $dispatcher,
        TextKnowledgeExtractor $extractor,
    ): RedirectResponse {
        $this->authorize('update', $project);
        $this->ensureUploadBelongsToProject($project, $upload);

        try {
            if (! $extractor->supports($upload->mime_type)) {
                $dispatcher->dispatch($upload);

                return back()->with('status', 'تمت إعادة جدولة معالجة الملف.');
            }

            $indexer->index($upload);
        } catch (KnowledgeExtractionException $exception) {
            return back()->with('error', 'تعذر استخراج نص صالح من الملف عند إعادة المحاولة.');
        }

        return back()->with('status', 'تمت إعادة استخراج معرفة الملف بنجاح.');
    }

    public function destroy(
        Request $request,
        Project $project,
        KnowledgeUpload $upload,
        StructuredKnowledgeRepository $repository,
    ): RedirectResponse {
        $this->authorize('update', $project);
        $project->loadMissing('workspace');
        $this->ensureUploadBelongsToProject($project, $upload);

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

        return back()->with('status', 'تم حذف مصدر المعرفة.');
    }

    /** يمنع الوصول لرفع لا يتبع هذا المشروع — عزل صارم للبيانات. */
    private function ensureUploadBelongsToProject(Project $project, KnowledgeUpload $upload): void
    {
        abort_unless((int) $upload->project_id === (int) $project->id, 404);
    }
}
