@extends('layouts.admin', ['title' => 'عروض القوالب', 'pageTitle' => 'عروض القوالب (الواجهة العامة)', 'pageKicker' => 'Marketing'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>العناصر</h2>
        <a href="{{ route('admin.marketing-template-highlights.create') }}" class="btn btn-primary btn-lg">عنصر جديد</a>
    </div>
    <p class="text-sm text-muted mb-4">تُعرض للزوار غير المسجلين في صفحة «القوالب». القوالب الفعلية للمستخدمين تُدار من «قوالب AI».</p>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>العنوان</th>
                    <th>الفئة</th>
                    <th>ترتيب</th>
                    <th>منشور</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td><strong>{{ $item->title }}</strong></td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->sort_order }}</td>
                        <td>{{ $item->is_published ? 'نعم' : 'لا' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.marketing-template-highlights.edit', $item) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.marketing-template-highlights.destroy', $item) }}" onsubmit="return confirm('حذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا عناصر.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $items->links() }}</div>
</section>
@endsection
