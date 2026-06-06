@extends('layouts.admin', ['title' => 'صفحات CMS', 'pageTitle' => 'صفحات CMS', 'pageKicker' => 'Marketing'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>الصفحات الثابتة (العناوين، النصوص القانونية، مقدمات الصفحات)</h2>
        <a href="{{ route('admin.cms-pages.create') }}" class="btn btn-primary btn-lg">صفحة جديدة</a>
    </div>
    <p class="text-sm text-muted mb-4">أمثلة للـ slug: <code>studio</code>، <code>templates</code>، <code>pricing</code>، <code>privacy</code>، <code>terms</code>، <code>contact</code>، <code>partnerships</code>، <code>about</code></p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>slug</th>
                    <th>العنوان</th>
                    <th>منشور</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pages as $p)
                    <tr>
                        <td><code>{{ $p->slug }}</code></td>
                        <td><strong>{{ $p->title }}</strong></td>
                        <td>{{ $p->is_published ? 'نعم' : 'لا' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.cms-pages.edit', $p) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.cms-pages.destroy', $p) }}" onsubmit="return confirm('حذف الصفحة؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا توجد صفحات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $pages->links() }}</div>
</section>
@endsection
