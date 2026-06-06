<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Models\BlogPost;
use App\Http\Controllers\Controller;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        return view('admin.blog-posts.index', [
            'posts' => BlogPost::query()->orderByDesc('published_at')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog-posts.form', [
            'post' => new BlogPost(['is_published' => false]),
            'method' => 'POST',
            'action' => route('admin.blog-posts.store'),
        ]);
    }

    public function store(Request $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['featured_image']);
        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        }
        $post = BlogPost::query()->create($data);

        return redirect()->route('admin.blog-posts.edit', $post)->with('status', $flash->created('المقال'));
    }

    public function edit(BlogPost $blogPost): View
    {
        return view('admin.blog-posts.form', [
            'post' => $blogPost,
            'method' => 'PUT',
            'action' => route('admin.blog-posts.update', $blogPost),
        ]);
    }

    public function update(Request $request, BlogPost $blogPost, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $this->validated($request, $blogPost->id);
        unset($data['featured_image']);
        if ($request->hasFile('featured_image')) {
            if ($blogPost->featured_image) {
                Storage::disk('public')->delete($blogPost->featured_image);
            }
            $data['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        }
        $blogPost->update($data);

        return back()->with('status', $flash->updated('المقال'));
    }

    public function destroy(BlogPost $blogPost, FlashMessageCatalog $flash): RedirectResponse
    {
        if ($blogPost->featured_image) {
            Storage::disk('public')->delete($blogPost->featured_image);
        }
        $blogPost->delete();

        return redirect()->route('admin.blog-posts.index')->with('status', $flash->deleted('المقال'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $slugRule = 'required|string|max:160|unique:blog_posts,slug';
        if ($exceptId !== null) {
            $slugRule .= ','.$exceptId;
        }

        $data = $request->validate([
            'title'                  => ['required', 'string', 'max:200'],
            'slug'                   => [$slugRule],
            'excerpt'                => ['nullable', 'string', 'max:500'],
            'body_html'              => ['required', 'string'],
            'featured_image'         => ['nullable', 'image', 'max:4096'],
            'featured_image_alt'     => ['nullable', 'string', 'max:200'],
            'og_image'               => ['nullable', 'string', 'max:500'],
            'category'               => ['nullable', 'string', 'max:100'],
            'tags'                   => ['nullable', 'string'],
            'reading_time_minutes'   => ['nullable', 'integer', 'min:1', 'max:999'],
            'author_name'            => ['nullable', 'string', 'max:100'],
            'author_title'           => ['nullable', 'string', 'max:150'],
            'meta_description'       => ['nullable', 'string', 'max:500'],
            'published_at'           => ['nullable', 'date'],
            'is_published'           => ['sometimes', 'boolean'],
            'is_featured'            => ['sometimes', 'boolean'],
            'sort_order'             => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured']  = $request->boolean('is_featured');

        if (empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        // تحويل التاغز من نص مفصول بفواصل إلى array
        if (isset($data['tags']) && is_string($data['tags'])) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
            $data['tags'] = array_values($data['tags']);
        }

        return $data;
    }
}
