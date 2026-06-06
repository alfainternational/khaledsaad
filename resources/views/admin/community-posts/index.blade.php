@extends('layouts.admin', ['title' => 'المجتمع', 'pageTitle' => 'مواضيع المجتمع', 'pageKicker' => 'Community'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>المواضيع</h2>
        <a href="{{ route('admin.community-posts.create') }}" class="btn btn-primary btn-lg">موضوع جديد</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>العنوان</th>
                    <th>slug</th>
                    <th>نشر</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td><strong>{{ $post->title }}</strong></td>
                        <td><code>{{ $post->slug }}</code></td>
                        <td>{{ $post->is_published ? 'نعم' : 'لا' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.community-posts.edit', $post) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.community-posts.destroy', $post) }}" onsubmit="return confirm('حذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا مواضيع.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $posts->links() }}</div>
</section>
@endsection
