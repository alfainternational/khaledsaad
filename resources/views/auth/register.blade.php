@extends('layouts.auth')
@section('layout', 'auth')

@section('title', 'إنشاء حساب')
@section('heading', $startTool !== null ? __('احفظ تقدمك في «:tool»', ['tool' => $startTool['title']]) : __('أنشئ حسابك واختر هدفك الحالي'))
{{-- نقول ما نفعله بالحساب بصيغة الحاضر، لا ما «يمنحه لك». --}}
@section('lead', __('حساب واحد يحفظ تقدم تعلمك وبيانات مشروعك، ويمكنك تفعيل المسار الآخر لاحقًا.'))

@section('context')
    @if ($startTool !== null)
        <div class="auth-intent" role="note">
            <span class="auth-intent__tag">{{ $startTool['category'] }}</span>
            <strong>{{ $startTool['title'] }}</strong>
            <p>{{ __('بعد تعريف مشروعك ستنتقل مباشرة إلى هذا التشخيص.') }}</p>
        </div>
    @endif

    @if ($startTool !== null)
        <ol class="auth-steps" aria-label="{{ __('ما يحدث بعد إنشاء الحساب') }}">
            <li class="is-current"><b>1</b> {{ __('إنشاء الحساب') }}</li>
            <li><b>2</b> {{ __('تعريف المشروع') }}</li>
            <li><b>3</b> {{ __('أسئلة التشخيص') }}</li>
            <li><b>4</b> {{ __('التقرير والمهام') }}</li>
        </ol>
    @endif
@endsection

@section('form')
    <form method="POST" action="{{ route('register') }}" class="form">
        @csrf

        <fieldset class="experience-choice">
            <legend>{{ __('ماذا تريد أن تفعل الآن؟') }}</legend>
            <label class="experience-choice__card">
                <input type="radio" name="experience" value="business" required
                    @checked(old('experience', $startExperience?->value) === 'business')>
                <span>
                    <strong>{{ __('أريد تحسين تسويق مشروعي') }}</strong>
                    <small>{{ __('أضف مشروعك، شخّص وضعه، واحصل على أولويات ومهام تتابعها.') }}</small>
                </span>
            </label>
            <label class="experience-choice__card">
                <input type="radio" name="experience" value="learning" required
                    @checked(old('experience', $startExperience?->value) === 'learning')>
                <span>
                    <strong>{{ __('أريد تعلّم التسويق بالتطبيق') }}</strong>
                    <small>{{ __('اتبع دروسًا عملية، طبّق ما تتعلمه، واحصل على تقييم يساعدك على التحسن.') }}</small>
                </span>
            </label>
            <p class="field__help">{{ __('يمكنك تفعيل المسار الآخر لاحقًا من حسابك دون إنشاء حساب جديد.') }}</p>
            @error('experience') <span class="field-error">{{ $message }}</span> @enderror
        </fieldset>

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
    <a href="{{ route('tools.index') }}">اطّلع على التشخيصات أولًا</a>
@endsection
