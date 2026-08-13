@extends('layouts.app')
@section('layout', 'index')

@section('title', 'المستخدمون')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة</p>
            <h1>المستخدمون</h1>
        </div>
        <div class="page-head__actions">
            <a href="{{ route('admin.users.plans.bulk') }}" class="btn btn--primary">تعيين خطة لمجموعة</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn--ghost">عودة</a>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.users.index') }}" class="inline-form">
        <input type="search" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو البريد" aria-label="بحث">
        <button type="submit" class="btn btn--ghost btn--sm">بحث</button>
    </form>

    <div class="table-wrap">
        <table class="table" data-table="entity">
            <thead>
                <tr><th>الاسم</th><th>البريد</th><th>الخطة</th><th>مساحات</th><th>رصيد</th><th>انضم</th><th>الإجراءات</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td data-label="الاسم">{{ $user['name'] }} @if ($user['is_admin'])<span class="badge">إدارة</span>@endif</td>
                        <td data-label="البريد">{{ $user['email'] }}</td>
                        <td data-label="الخطة">{{ $user['plan'] }}</td>
                        <td data-label="مساحات">{{ $user['workspaces'] }}</td>
                        <td data-label="رصيد">{{ $user['balance'] }}</td>
                        <td data-label="انضم">{{ $user['joined'] }}</td>
                        <td class="table__actions" data-label="الإجراءات">
                            <a href="{{ route('admin.users.edit', $user['id']) }}" class="btn btn--ghost btn--sm">تعديل</a>
                            @unless ($user['is_admin'])
                                <form method="POST" action="{{ route('admin.users.impersonate', $user['id']) }}" class="inline-form"
                                    data-confirm="{{ __('ستدخل بحساب :name وتُسجَّل الجلسة في التدقيق. متأكد؟', ['name' => $user['name']]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn--ghost btn--sm">ادخل بحسابه</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('admin.users.credits', $user['id']) }}" class="inline-form">
                                @csrf
                                <input type="number" name="credits" min="1" placeholder="عدد" required aria-label="عدد الرصيد" style="max-width: 5rem;">
                                <button type="submit" class="btn btn--ghost btn--sm">رصيد</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" data-label="">لا يوجد مستخدم يطابق البحث. جرّب الاسم أو البريد بصيغة أخرى.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
