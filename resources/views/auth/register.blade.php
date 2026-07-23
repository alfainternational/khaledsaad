@extends('layouts.auth')

@section('title', 'إنشاء حساب')
@section('heading', $startTool !== null ? 'خطوة واحدة قبل «'.$startTool['title'].'»' : 'ابدأ تشخيص مشروعك')
@section('lead', 'الحساب يحفظ إجاباتك وتقاريرك ويتيح مقارنة تقدمك لاحقًا.')

@section('context')
    @if ($startTool !== null)
        <div class="auth-intent" role="note">
            <span class="auth-intent__tag">{{ $startTool['category'] }}</span>
            <strong>{{ $startTool['title'] }}</strong>
            <p>ستفتح لك هذه الأداة مباشرة بعد تعريف مشروعك.</p>
        </div>
    @endif

    <ol class="auth-steps" aria-label="ما الذي سيحدث بعد ذلك">
        <li class="is-current"><b>1</b> إنشاء الحساب</li>
        <li><b>2</b> تعريف المشروع</li>
        <li><b>3</b> {{ $startTool !== null ? 'أسئلة الأداة' : 'اختيار الأداة' }}</li>
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

        <button type="submit" class="btn btn--primary btn--block">إنشاء الحساب</button>
    </form>
@endsection

@section('alt')
    لديك حساب؟ <a href="{{ route('login', $startTool !== null ? ['tool' => $startTool['key']] : []) }}">سجّل الدخول</a>
    <span class="auth-card__sep" aria-hidden="true">·</span>
    <a href="{{ route('tools.index') }}">استعرض الأدوات أولًا</a>
@endsection
