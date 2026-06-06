@extends('layouts.admin', ['title' => 'دراسات الحالة', 'pageTitle' => 'دراسات الحالة', 'pageKicker' => 'Case studies'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>القائمة</h2>
        <a href="{{ route('admin.case-studies.create') }}" class="btn btn-primary btn-lg">دراسة جديدة</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>العنوان</th>
                    <th>العميل</th>
                    <th>نشر</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $study)
                    <tr>
                        <td><strong>{{ $study->title }}</strong></td>
                        <td>{{ $study->client_name }}</td>
                        <td>{{ $study->is_published ? 'نعم' : 'لا' }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.case-studies.edit', $study) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.case-studies.destroy', $study) }}" onsubmit="return confirm('حذف؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا عناصر.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $items->links() }}</div>
</section>
@endsection
