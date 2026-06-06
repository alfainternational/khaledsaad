@extends('layouts.admin', ['title' => 'الشركاء', 'pageTitle' => 'شركاء صفحة الشراكات', 'pageKicker' => 'Partners'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>القائمة</h2>
        <a href="{{ route('admin.partners.create') }}" class="btn btn-primary btn-lg">شريك جديد</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>ترتيب</th>
                    <th>منشور</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partners as $partner)
                    <tr>
                        <td><strong>{{ $partner->name }}</strong></td>
                        <td>{{ $partner->sort_order }}</td>
                        <td>{{ $partner->is_published ? 'نعم' : 'لا' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.partners.edit', $partner) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.partners.destroy', $partner) }}" onsubmit="return confirm('حذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا شركاء.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $partners->links() }}</div>
</section>
@endsection
