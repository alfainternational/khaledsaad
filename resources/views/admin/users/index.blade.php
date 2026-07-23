@extends('layouts.app')

@section('title', 'المستخدمون')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>المستخدمون</h1>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="GET" action="{{ route('admin.users.index') }}" class="inline-form">
        <input type="search" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو البريد" aria-label="بحث">
        <button type="submit" class="btn btn--ghost btn--sm">بحث</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>الاسم</th><th>البريد</th><th>مساحات</th><th>رصيد</th><th>انضم</th><th>منح رصيد</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user['name'] }} @if ($user['is_admin'])<span class="badge">إدارة</span>@endif</td>
                        <td>{{ $user['email'] }}</td>
                        <td>{{ $user['workspaces'] }}</td>
                        <td>{{ $user['balance'] }}</td>
                        <td>{{ $user['joined'] }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.users.edit', $user['id']) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            <form method="POST" action="{{ route('admin.users.credits', $user['id']) }}" class="inline-form">
                                @csrf
                                <input type="number" name="credits" min="1" placeholder="عدد" required aria-label="عدد الرصيد" style="max-width: 5rem;">
                                <button type="submit" class="btn btn--ghost btn--sm">رصيد</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">لا نتائج.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
