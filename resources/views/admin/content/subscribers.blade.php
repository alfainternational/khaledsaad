@extends('layouts.app')
@section('layout', 'index')
@section('title', 'مشتركو المحتوى')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">المكتبة</p>
            <h1>مشتركو المحتوى</h1>
            <p class="muted">عناوين البريد التي وافق أصحابها على التسجيل لفتح المحتوى.</p>
        </div>
        <a href="{{ route('admin.content-subscribers.export') }}" class="btn btn--primary">تصدير CSV</a>
    </header>

    @if (session('success')) <div class="alert alert--success">{{ session('success') }}</div> @endif

    <form method="GET" class="form filter-bar">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="ابحث بالبريد الإلكتروني" dir="ltr">
        <select name="status">
            <option value="">كل الحالات</option>
            <option value="active" @selected(request('status') === 'active')>نشط</option>
            <option value="disabled" @selected(request('status') === 'disabled')>موقوف</option>
        </select>
        <button class="btn btn--ghost">تصفية</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>البريد</th><th>الحالة</th><th>تاريخ الموافقة</th><th></th></tr></thead>
            <tbody>
                @forelse ($subscribers as $subscriber)
                    <tr>
                        <td dir="ltr">{{ $subscriber->email }}</td>
                        <td>{{ $subscriber->status === 'active' ? 'نشط' : 'موقوف' }}</td>
                        <td>{{ $subscriber->consented_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.content-subscribers.status', $subscriber) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $subscriber->status === 'active' ? 'disabled' : 'active' }}">
                                <button class="btn btn--ghost btn--sm">{{ $subscriber->status === 'active' ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">لا يوجد مشتركون بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $subscribers->links() }}
@endsection
