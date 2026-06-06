<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\CommunityPost;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityPostController extends Controller
{
    public function index(): View
    {
        return view('admin.community-posts.index', [
            'posts' => CommunityPost::query()->orderByDesc('published_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.community-posts.form', [
            'post' => new CommunityPost(['is_published' => false]),
            'method' => 'POST',
            'action' => route('admin.community-posts.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        $post = CommunityPost::query()->create($data);

        return redirect()->route('admin.community-posts.edit', $post)->with('status', $flash->created('موضوع المجتمع'));
    }

    public function edit(CommunityPost $communityPost): View
    {
        return view('admin.community-posts.form', [
            'post' => $communityPost,
            'method' => 'PUT',
            'action' => route('admin.community-posts.update', $communityPost),
        ]);
    }

    public function update(Request $request, CommunityPost $communityPost, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request, $communityPost->id);
        $communityPost->update($data);

        return back()->with('status', $flash->updated('موضوع المجتمع'));
    }

    public function destroy(CommunityPost $communityPost, FlashMessageCatalog $flash): RedirectResponse
    {
        $communityPost->delete();

        return redirect()->route('admin.community-posts.index')->with('status', $flash->deleted('موضوع المجتمع'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $slugRule = 'required|string|max:160|unique:community_posts,slug';
        if ($exceptId !== null) {
            $slugRule .= ','.$exceptId;
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => [$slugRule],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body_html' => ['required', 'string'],
            'author_display_name' => ['nullable', 'string', 'max:120'],
            'published_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        if (empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        return $data;
    }
}
