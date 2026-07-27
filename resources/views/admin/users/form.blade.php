@extends('layouts.app')
@section('layout', 'form')

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

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="form form--wide form-layout">
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

    <section class="card" aria-labelledby="plan-heading">
        <h2 id="plan-heading">إدارة الخطة</h2>
        <p class="muted">اختر مساحة العمل والخطة وموعد النفاذ. لا يتغير الرصيد إلا إذا اخترت ذلك صراحة.</p>
        <form method="POST" action="{{ route('admin.users.plan.assign', $user) }}" class="form form--wide form-layout">
            @csrf
            <label class="field"><span class="field__label">مساحة العمل</span>
                <select name="workspace_id" required>
                    @foreach($workspaces as $workspace)
                        <option value="{{ $workspace->id }}">{{ $workspace->name }} — {{ $workspace->subscription?->plan?->name ?? 'بلا خطة' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field"><span class="field__label">الخطة الجديدة</span>
                <select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}{{ $plan->is_public ? '' : ' (خاصة)' }}</option>@endforeach</select>
            </label>
            <label class="field"><span class="field__label">موعد التطبيق</span>
                <select name="effective"><option value="now">فورًا</option><option value="period_end">نهاية الفترة الحالية</option></select>
            </label>
            <label class="field"><span class="field__label">معالجة الرصيد</span>
                <select name="credit_policy"><option value="keep">إبقاء الرصيد كما هو</option><option value="plan_grant">إضافة رصيد الخطة</option><option value="add">إضافة مقدار محدد</option></select>
            </label>
            <label class="field"><span class="field__label">مقدار إضافي عند اختيار الإضافة</span><input type="number" name="credit_amount" min="1"></label>
            <label><input type="checkbox" name="confirmation" value="1" required> أؤكد تغيير الخطة وفق الخيارات أعلاه.</label>
            <button class="btn btn--primary" type="submit">طبّق وسجّل القرار</button>
        </form>
    </section>

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
