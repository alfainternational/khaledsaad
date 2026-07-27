@extends('layouts.public')
@section('layout', 'wizard')

@section('title', $run['tool']['title'].' | تجربة من دون حساب')

@section('content')
    @include('partials.site-header')

    @php
        $known = array_values(array_filter($step['fields'], fn ($field) => ! empty($field['is_known'])));
        $fresh = array_values(array_filter($step['fields'], fn ($field) => empty($field['is_known'])));
    @endphp

    <main id="main-content" class="try-shell">
        <div class="container try-layout">
            <header class="try-head">
                <p class="eyebrow">{{ $run['tool']['title'] }}</p>
                <h1>{{ $step['title'] }}</h1>
                <div class="progress" role="group" aria-label="إلى أين وصلت">
                    <div class="progress__bar">
                        <span style="inline-size: {{ (int) round($position / max(1, $total_steps) * 100) }}%"></span>
                    </div>
                    <p class="muted">الخطوة {{ $position }} من {{ $total_steps }} · يمكنك المتابعة الآن من دون حساب</p>
                </div>
            </header>

            @if ($errors->any())
                <div class="alert alert--error" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('try.step.save', [$run['uuid'], $step_number]) }}" class="form form--wide">
                @csrf

                @if ($known !== [])
                    <details class="known-block">
                        <summary>
                            <b>{{ count($known) }}</b>
                            إجابات نعرفها من خطوة سابقة — لن نسألك عنها مرة أخرى
                            <span>افتحها إن أردت تعديلها</span>
                        </summary>
                        <div class="known-block__body">
                            @foreach ($known as $field)
                                @include('app.runs.partials.field', ['field' => $field])
                            @endforeach
                        </div>
                    </details>
                @endif

                @foreach ($fresh as $field)
                    @include('app.runs.partials.field', ['field' => $field])
                @endforeach

                <div class="form__actions">
                    @if ($previous_step !== null)
                        <a href="{{ route('try.step', [$run['uuid'], $previous_step]) }}" class="btn btn--ghost">السابق</a>
                    @endif
                    <button type="submit" class="btn btn--primary">
                        {{ $next_step === null ? 'اعرض النتيجة الأولية' : 'احفظ وانتقل للسؤال التالي' }}
                    </button>
                </div>
            </form>

            <p class="try-note">
                تُحفظ إجاباتك على هذا الجهاز لمدة ثلاثين يومًا، وتنتقل إلى حسابك كما هي إذا أنشأته لاحقًا.
            </p>
        </div>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
