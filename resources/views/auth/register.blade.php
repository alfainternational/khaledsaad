@extends('layouts.auth')

@section('title', 'إنشاء حساب')
@section('heading', $startTool !== null ? 'احفظ تقدمك في «'.$startTool['title'].'»' : 'أنشئ حسابك وابدأ تشخيص مشروعك')
@section('lead', 'يجمع حسابك إجاباتك وتقاريرك ومهامك في مكان واحد، لتعود إليها وتتابع تقدم مشروعك.')

@section('context')
    @if ($startTool !== null)
        <div class="auth-intent" role="note">
            <span class="auth-intent__tag">{{ $startTool['category'] }}</span>
            <strong>{{ $startTool['title'] }}</strong>
            <p>بعد تعريف مشروعك ستنتقل مباشرة إلى هذا التشخيص.</p>
        </div>
    @endif

    <ol class="auth-steps" aria-label="ما الذي سيحدث بعد ذلك">
        <li class="is-current"><b>1</b> إنشاء الحساب</li>
        <li><b>2</b> تعريف المشروع</li>
        <li><b>3</b> {{ $startTool !== null ? 'أسئلة التشخيص' : 'اختيار التشخيص' }}</li>
        <li><b>4</b> التقرير والمهام</li>
    </ol>
@endsection

@section('form')
    <form method="POST" action="{{ route('register') }}" class="form">
        @csrf

        <label class="field">
            <span class="field__label">الاسم</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        </label>

        <label class="field">
            <span class="field__label">البريد الإلكتروني</span>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>

        <label class="field">
            <span class="field__label">كلمة المرور</span>
            <input type="password" name="password" required autocomplete="new-password">
            <span class="field__help">ثمانية أحرف على الأقل.</span>
        </label>

        <label class="field">
            <span class="field__label">تأكيد كلمة المرور</span>
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>

        <button type="submit" class="btn btn--primary btn--block">أنشئ حسابك وتابع</button>
    </form>
@endsection

@section('alt')
    لديك حساب؟ <a href="{{ route('login', $startTool !== null ? ['tool' => $startTool['key']] : []) }}">سجّل الدخول</a>
    <span class="auth-card__sep" aria-hidden="true">·</span>
    <a href="{{ route('tools.index') }}">استكشف التشخيصات أولًا</a>
@endsection
