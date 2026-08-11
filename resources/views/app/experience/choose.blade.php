@extends('layouts.app')
@section('layout', 'form')
@section('title', __('اختر ما تريد أن تفعله الآن'))

@section('content')
    <div class="page-header">
        <div>
            <span class="eyebrow">{{ __('بداية واحدة، ومساران يمكنك الجمع بينهما') }}</span>
            <h1>{{ __('ماذا تريد أن تفعل الآن؟') }}</h1>
            <p>{{ __('اختر هدفك الحالي. يمكنك تفعيل المسار الآخر لاحقًا من حسابك دون إنشاء حساب جديد.') }}</p>
        </div>
    </div>

    @if ($user->initial_experience === null)
    <form method="POST" action="{{ route('app.experience.select') }}" class="experience-choice form-layout">
        @csrf
        <fieldset>
            <legend class="sr-only">{{ __('اختر هدفك الحالي') }}</legend>
            <label class="experience-choice__card">
                <input type="radio" name="experience" value="business" required @checked(old('experience') === 'business')>
                <span>
                    <strong>{{ __('أريد تحسين تسويق مشروعي') }}</strong>
                    <small>{{ __('أضف مشروعك، شخّص وضعه، واحصل على أولويات ومهام تتابعها.') }}</small>
                </span>
            </label>
            <label class="experience-choice__card">
                <input type="radio" name="experience" value="learning" required @checked(old('experience') === 'learning')>
                <span>
                    <strong>{{ __('أريد تعلّم التسويق بالتطبيق') }}</strong>
                    <small>{{ __('اتبع دروسًا عملية، طبّق ما تتعلمه، واحصل على تقييم يساعدك على التحسن.') }}</small>
                </span>
            </label>
        </fieldset>
        @error('experience') <p class="field-error">{{ $message }}</p> @enderror
        <button class="btn btn--primary" type="submit">{{ __('ابدأ مساري') }}</button>
    </form>
    @else
        <div class="experience-choice form-layout">
            @foreach (\App\Support\Experience\Experience::cases() as $experience)
                @php($isLearning = $experience === \App\Support\Experience\Experience::LEARNING)
                @php($isEnabled = in_array($experience->value, $enabledExperiences, true))
                @php($isActive = $user->activeExperience() === $experience)
                <section class="experience-choice__card">
                    <div>
                        <strong>{{ $isLearning ? __('تعلّم التسويق بالتطبيق') : __('تحسين تسويق مشروعي') }}</strong>
                        <p>{{ $isLearning
                            ? __('دروس عملية وتطبيقات وتقييم يساعدك على التحسن.')
                            : __('مشاريع وتشخيصات وأولويات ومهام وتقارير.') }}</p>
                        @if ($isActive)<span class="tag">{{ __('تعمل عليه الآن') }}</span>@endif
                    </div>

                    @if (! $isEnabled)
                        <a class="btn btn--primary" href="{{ route('app.experience.activate', $experience->value) }}">
                            {{ $isLearning ? __('فعّل مسار التعلم') : __('فعّل مسار الأعمال') }}
                        </a>
                    @elseif (! $isActive)
                        <form method="POST" action="{{ route('app.experience.switch', $experience->value) }}">
                            @csrf
                            <button class="btn btn--primary" type="submit">
                                {{ $isLearning ? __('انتقل إلى مسار التعلم') : __('انتقل إلى مسار الأعمال') }}
                            </button>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
@endsection
