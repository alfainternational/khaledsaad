@extends('layouts.admin', ['title' => 'قوالب AI', 'pageTitle' => 'إدارة قوالب AI', 'pageKicker' => 'AI Templates'])

@section('content')
<section class="admin-panel mb-6">
    <div class="admin-panel-head">
        <h2>قوالب الاستوديو</h2>
        <a href="{{ route('admin.ai-templates.create') }}" class="btn btn-primary btn-lg">قالب جديد</a>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>القالب</th>
                    <th>الموديل</th>
                    <th>الاعتمادات</th>
                    <th>الحالة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>
                            <strong>{{ $template->name }}</strong>
                            <small>{{ $template->code }}</small>
                        </td>
                        <td>{{ $template->model }}</td>
                        <td>{{ $template->credit_cost }}</td>
                        <td>{{ $template->status }}</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.ai-templates.edit', $template) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.ai-templates.destroy', $template) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد قوالب بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-pagination">{{ $templates->links() }}</div>
</section>
@endsection
