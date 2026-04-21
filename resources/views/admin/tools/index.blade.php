@extends('layouts.admin', ['title' => 'الأدوات', 'pageTitle' => 'إدارة الأدوات', 'pageKicker' => 'Tools'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>كتالوج الأدوات</h2>
        <a href="{{ route('admin.tools.create') }}" class="btn btn-primary btn-lg">أداة جديدة</a>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>الأداة</th>
                    <th>المرحلة</th>
                    <th>الموديول</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tools as $tool)
                    <tr>
                        <td>
                            <strong>{{ $tool->name }}</strong>
                            <small>{{ $tool->code }}</small>
                        </td>
                        <td>{{ $tool->stage }}</td>
                        <td>{{ $tool->module ?? '—' }}</td>
                        <td>{{ $tool->status }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.tools.edit', $tool) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.tools.destroy', $tool) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد أدوات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $tools->links() }}</div>
</section>
@endsection
