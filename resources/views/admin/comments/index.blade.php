@extends('layouts.admin', ['title' => 'التعليقات', 'pageTitle' => 'إدارة التعليقات', 'pageKicker' => 'المراقبة'])

@section('content')
<section class="admin-toolbar">
    <form method="GET" class="admin-filters">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث في المحتوى…" class="admin-input">
        <input type="number" name="workspace_id" value="{{ request('workspace_id') }}" placeholder="معرّف مساحة العمل" class="admin-input" min="1">
        <button type="submit" class="btn btn-secondary">بحث</button>
    </form>
</section>

<section class="admin-panel panel-modern">
    <div class="admin-panel-head">
        <h2>التعليقات <small>({{ $comments->total() }})</small></h2>
        <form method="POST" action="{{ route('admin.comments.bulk-destroy') }}" id="admin-comments-bulk" class="admin-inline-actions" onsubmit="return confirm('حذف التعليقات المحددة نهائياً؟')">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm" data-bulk-submit="1" disabled>حذف المحدد</button>
        </form>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><span class="sr-only">تحديد</span></th>
                    <th>#</th>
                    <th>الكاتب</th>
                    <th>مساحة العمل</th>
                    <th>النوع</th>
                    <th>المحتوى</th>
                    <th>التاريخ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($comments as $comment)
                    <tr>
                        <td>
                            <input type="checkbox" name="comment_ids[]" value="{{ $comment->id }}" class="bulk-comment-cb" form="admin-comments-bulk" data-bulk-cb>
                        </td>
                        <td>{{ $comment->id }}</td>
                        <td>{{ $comment->author?->name ?? '—' }}</td>
                        <td>{{ $comment->workspace?->name ?? '—' }}</td>
                        <td><span class="app-badge">{{ $comment->entity_type }}</span></td>
                        <td>{{ Str::limit($comment->body, 80) }}</td>
                        <td>{{ $comment->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}" onsubmit="return confirm('حذف هذا التعليق؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="admin-empty">لا توجد تعليقات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $comments->links() }}</div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-bulk-cb]').forEach(function (el) {
    el.addEventListener('change', function () {
        var any = document.querySelector('.bulk-comment-cb:checked');
        document.querySelectorAll('[data-bulk-submit]').forEach(function (btn) {
            btn.disabled = !any;
        });
    });
});
</script>
@endpush
