<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Comment\Models\Comment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDestroyCommentsRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentModerationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $comments = Comment::query()
            ->with(['workspace', 'author'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->value();
                $query->where('body', 'like', "%{$search}%");
            })
            ->when($request->integer('workspace_id') > 0, fn ($query) => $query->where('workspace_id', $request->integer('workspace_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.comments.index', ['comments' => $comments]);
    }

    public function destroy(Comment $comment, FlashMessageCatalog $flash): RedirectResponse
    {
        $id = $comment->id;
        $comment->delete();

        $this->auditLogger->record(
            action: 'admin.comment.deleted',
            targetType: 'comment',
            targetId: $id,
            actor: request()->user(),
        );

        return back()->with('status', $flash->deleted('التعليق'));
    }

    public function bulkDestroy(BulkDestroyCommentsRequest $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $ids = $request->validated('comment_ids');
        $comments = Comment::query()->whereIn('id', $ids)->get();

        foreach ($comments as $comment) {
            $id = $comment->id;
            $comment->delete();
            $this->auditLogger->record(
                action: 'admin.comment.deleted',
                targetType: 'comment',
                targetId: $id,
                actor: $request->user(),
            );
        }

        return back()->with('status', $flash->deleted('التعليقات المحددة'));
    }
}
