@extends('layouts.admin', ['title' => 'المدونة', 'pageTitle' => 'مقالات المدونة', 'pageKicker' => 'Blog'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>المقالات</h2>
        <a href="{{ route('admin.blog-posts.create') }}" class="btn btn-primary btn-lg">مقال جديد</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>العنوان</th>
                    <th>التصنيف</th>
                    <th>الكاتب</th>
                    <th>مميز</th>
                    <th>منشور</th>
                    <th>المشاهدات</th>
                    <th>تاريخ النشر</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td>
                            <strong>{{ $post->title }}</strong>
                            <br><small class="text-gray-400" dir="ltr">{{ $post->slug }}</small>
                        </td>
                        <td>{{ $post->category ?? '—' }}</td>
                        <td>{{ $post->author_name ?? '—' }}</td>
                        <td>
                            @if($post->is_featured)
                                <span class="badge badge-warning">مميز</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            @if($post->is_published)
                                <span class="badge badge-success">نعم</span>
                            @else
                                <span class="badge badge-ghost">لا</span>
                            @endif
                        </td>
                        <td>{{ number_format($post->view_count ?? 0) }}</td>
                        <td>{{ $post->published_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="btn btn-ghost btn-sm">عرض</a>
                            <a href="{{ route('admin.blog-posts.edit', $post) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.blog-posts.destroy', $post) }}" onsubmit="return confirm('حذف هذا المقال؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-8">لا توجد مقالات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $posts->links() }}</div>
</section>
@endsection
