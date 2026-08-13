@extends('layouts.app')
@section('layout', 'form')
@section('title', $experience === \App\Support\Experience\Experience::LEARNING ? __('فعّل مسار التعلم') : __('فعّل مسار الأعمال'))

@section('content')
    <div class="page-header">
        <div>
            <span class="eyebrow">{{ __('داخل حسابك الحالي') }}</span>
            <h1>{{ $experience === \App\Support\Experience\Experience::LEARNING ? __('فعّل مسار التعلم') : __('فعّل مسار الأعمال') }}</h1>
            <p>
                {{ $experience === \App\Support\Experience\Experience::LEARNING
                    ? __('ستظهر الدروس وتطبيقاتها وتقدمك. تفعيل المسار لا يغيّر باقتك الحالية.')
                    : __('ستظهر المشاريع والتشخيصات والمهام والتقارير. تفعيل المسار لا يغيّر باقتك الحالية.') }}
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('app.experience.enable', $experience->value) }}">
        @csrf
        <button class="btn btn--primary" type="submit">
            {{ $experience === \App\Support\Experience\Experience::LEARNING ? __('فعّل مسار التعلم') : __('فعّل مسار الأعمال') }}
        </button>
    </form>
@endsection
