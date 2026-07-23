@extends('layouts.app')

@section('title', 'تعديل مستخدم')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">الإدارة · المستخدمون</p>
            <h1>{{ $user->name }}</h1>
            <p class="muted">الرصيد الحالي: {{ $balance }}</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="btn btn--ghost">عودة</a>
    </header>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="form form--wide">
        @csrf @method('PUT')

        <label class="field">
            <span class="field__label">الاسم</span>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
        </label>

        <label class="field">
            <span class="field__label">البريد</span>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
        </label>

        <button type="submit" class="btn btn--primary">حفظ</button>
    </form>

    <section class="split">
        <article class="card">
            <p class="eyebrow">منح رصيد</p>
            <form method="POST" action="{{ route('admin.users.credits', $user) }}" class="inline-form">
                @csrf
                <input type="number" name="credits" min="1" placeholder="عدد الأرصدة" required>
                <button type="submit" class="btn btn--ghost btn--sm">أضف</button>
            </form>
        </article>

        <article class="card">
            <p class="eyebrow">صلاحية الإدارة</p>
            <p class="muted">{{ $user->isAdmin() ? 'هذا المستخدم آدمن.' : 'مستخدم عادي.' }}</p>
            <form method="POST" action="{{ route('admin.users.admin', $user) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn--ghost btn--sm">
                    {{ $user->isAdmin() ? 'نزع الصلاحية' : 'منح صلاحية الإدارة' }}
                </button>
            </form>
        </article>
    </section>
@endsection
