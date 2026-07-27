@extends('layouts.app')
@section('layout', 'index')

@section('title', 'إدارة الأدوات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>الأدوات</h1>
        </div>
        <a href="{{ route('admin.tools.create') }}" class="btn btn--primary">أداة جديدة</a>
    </header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>الأداة</th><th>الفئة</th><th>الرصيد</th><th>تشغيلات</th><th>تكلفة</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($tools as $tool)
                    <tr>
                        <td><a href="{{ route('admin.tools.show', $tool['key']) }}">{{ $tool['title'] }}</a></td>
                        <td>{{ $tool['category'] }}</td>
                        <td>{{ $tool['credit_cost'] ?? '—' }}</td>
                        <td>{{ $tool['runs'] }}</td>
                        <td>{{ $tool['cost_usd'] }}$</td>
                        <td>{{ $tool['status'] === 'published' ? 'منشورة' : 'قريبًا' }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.tools.edit', $tool['key']) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.tools.status', $tool['key']) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $tool['status'] === 'published' ? 'coming_soon' : 'published' }}">
                                <button type="submit" class="btn btn--ghost btn--sm">
                                    {{ $tool['status'] === 'published' ? 'إخفاء' : 'نشر' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.tools.destroy', $tool['key']) }}" onsubmit="return confirm('حذف هذه الأداة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn--ghost btn--sm">حذف</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
