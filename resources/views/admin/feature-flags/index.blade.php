@extends('layouts.admin', ['title' => 'Feature Flags', 'pageTitle' => 'إدارة Feature Flags', 'pageKicker' => 'Flags'])

@section('content')
<section class="admin-panel">
    <div class="admin-panel-head">
        <h2>الفلاجز الحالية</h2>
        <a href="{{ route('admin.feature-flags.create') }}" class="btn btn-primary btn-lg">فلاج جديد</a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>المفتاح</th>
                    <th>الاسم</th>
                    <th>الموديول</th>
                    <th>الحالة</th>
                    <th>الـ Rollout</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($featureFlags as $flag)
                    <tr>
                        <td>{{ $flag->key }}</td>
                        <td>{{ $flag->name }}</td>
                        <td>{{ $flag->module ?? '—' }}</td>
                        <td>{{ $flag->status->value }}</td>
                        <td>{{ $flag->rollout_percentage }}%</td>
                        <td class="admin-actions-cell">
                            <a href="{{ route('admin.feature-flags.edit', $flag) }}" class="btn btn-secondary btn-sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.feature-flags.destroy', $flag) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا توجد Feature Flags بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-pagination">
        {{ $featureFlags->links() }}
    </div>
</section>
@endsection
