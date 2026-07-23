@extends('layouts.public')

@section('title', $run['tool']['title'].' | تجربة بدون حساب')

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
                        <span style="inline-size: {{ (int) round($step_number / max(1, $total_steps) * 100) }}%"></span>
                    </div>
                    <p class="muted">الخطوة {{ $step_number }} من {{ $total_steps }} · تجرّب الآن بدون حساب</p>
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
                    @if ($step_number > 1)
                        <a href="{{ route('try.step', [$run['uuid'], $step_number - 1]) }}" class="btn btn--ghost">السابق</a>
                    @endif
                    <button type="submit" class="btn btn--primary">
                        {{ $step_number >= $total_steps ? 'شوف نتيجتك' : 'التالي' }}
                    </button>
                </div>
            </form>

            <p class="try-note">
                إجاباتك محفوظة على هذا الجهاز لمدة ثلاثين يومًا. لو أنشأت حسابًا لاحقًا تنتقل معك كما هي.
            </p>
        </div>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
