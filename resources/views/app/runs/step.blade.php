@extends('layouts.app')

@section('title', $run['tool']['title'])

@section('content')
    @php
        // ما نعرفه من قبل يُعرض مطويًا، والأسئلة الجديدة تظهر أولًا:
        // المستخدم لا يعيد كتابة ما كتبه في أداة سابقة.
        $known = array_values(array_filter($step['fields'], fn ($field) => ! empty($field['is_known'])));
        $fresh = array_values(array_filter($step['fields'], fn ($field) => empty($field['is_known'])));
    @endphp

    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $run['tool']['title'] }} · {{ $run['project']['name'] }}</p>
            <h1>{{ $step['title'] }}</h1>
        </div>
    </header>

    <div class="progress" role="group" aria-label="إلى أين وصلت">
        <div class="progress__bar">
            <span style="inline-size: {{ (int) round($step_number / max(1, $total_steps) * 100) }}%"></span>
        </div>
        <p class="muted">الخطوة {{ $step_number }} من {{ $total_steps }} · إجاباتك تُحفظ تلقائيًا بعد كل خطوة</p>
    </div>

    <form method="POST" action="{{ route('app.runs.step.save', [$run['uuid'], $step_number]) }}" class="form form--wide">
        @csrf

        @if ($known !== [])
            <details class="known-block">
                <summary>
                    <b>{{ count($known) }}</b>
                    @if (count($known) === 1)
                        إجابة نعرفها عنك من قبل — لن نسألك عنها مرة أخرى
                    @else
                        إجابات نعرفها عنك من قبل — لن نسألك عنها مرة أخرى
                    @endif
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

        @if ($fresh === [])
            <p class="alert alert--info" role="status">
                كل أسئلة هذه الخطوة إجاباتها موجودة عندنا. راجعها فوق أو أكمل مباشرة.
            </p>
        @endif

        <div class="form__actions">
            @if ($step_number > 1)
                <a href="{{ route('app.runs.step', [$run['uuid'], $step_number - 1]) }}" class="btn btn--ghost">السابق</a>
            @endif
            <button type="submit" class="btn btn--primary">
                {{ $step_number >= $total_steps ? 'راجع إجاباتك' : 'التالي' }}
            </button>
        </div>
    </form>
@endsection
