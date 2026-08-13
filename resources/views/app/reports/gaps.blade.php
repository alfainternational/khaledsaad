@extends('layouts.app')
@section('layout', 'form')

@section('title', __('أكمل ما ينقص تشخيصك'))

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $project->name }}</p>
            <h1>{{ __('أكمل ما ينقص تشخيصك') }}</h1>
            <p class="muted">
                {{ __('هذه هي المعلومات التي لم نجدها عنك، وكل واحدة منها غيّرت شيئًا في تقريرك. اكتب ما تعرفه واترك ما لا تعرفه — الفراغ المعلن أصدق من تخمين.') }}
            </p>
        </div>
    </header>

    @if ($errors->any())
        <div class="card card--warn" role="alert">
            <ul class="bullets">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('app.reports.gaps.update', $report) }}" class="form form--wide form-layout question-form">
        @csrf
        @method('PUT')

        @foreach ($gaps as $gap)
            <section class="card">
                <label class="field">
                    <span class="field__label">{{ $gap['label'] }}</span>

                    @if (! empty($gap['help']))
                        <span class="field__help">{{ $gap['help'] }}</span>
                    @endif

                    @if ($gap['type'] === 'select')
                        <select name="answers[{{ $gap['key'] }}]">
                            <option value="">{{ __('اختر…') }}</option>
                            @foreach ($gap['options'] as $option)
                                <option value="{{ $option['value'] }}" @selected(old('answers.'.$gap['key']) === (string) $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @elseif ($gap['type'] === 'number')
                        <input type="number" name="answers[{{ $gap['key'] }}]" value="{{ old('answers.'.$gap['key']) }}" min="0">
                    @elseif ($gap['type'] === 'text')
                        <input type="text" name="answers[{{ $gap['key'] }}]" value="{{ old('answers.'.$gap['key']) }}" maxlength="200">
                    @else
                        <textarea name="answers[{{ $gap['key'] }}]" rows="3" maxlength="2000">{{ old('answers.'.$gap['key']) }}</textarea>
                    @endif
                </label>

                @if (! empty($gap['why']))
                    <p class="question-reason">{{ __('لماذا يهم:') }} {{ $gap['why'] }}</p>
                @endif

                @include('app.partials.question-assist', [
                    'projectSlug' => $project->slug,
                    'surface' => $gap['surface'],
                    'questionKey' => $gap['key'],
                    'fieldKey' => $gap['key'],
                    'answerType' => $gap['type'],
                    'inputName' => 'answers['.$gap['key'].']',
                    'runUuid' => $runUuid,
                ])
            </section>
        @endforeach

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">{{ __('احفظ ما كتبته') }}</button>
            <a class="btn btn--ghost" href="{{ route('app.reports.show', $report) }}">{{ __('ارجع إلى التقرير') }}</a>
        </div>

        {{--
            الحفظ لا يعيد حساب تقرير صدر: التقرير مستند مؤرَّخ، وتعديله بأثر
            رجعيّ يجعل نسخته المطبوعة تخالف نسخته على الشاشة. الإجابات تدخل
            ذاكرة المشروع فورًا، ويقرؤها التشخيص التالي بلا إعادة كتابة.
        --}}
        <p class="muted">
            {{ __('ما تكتبه هنا يُحفظ في ملف نشاطك فورًا، ولن نسألك عنه مرة أخرى. تقريرك الحالي يبقى كما صدر، والتشخيص القادم يبدأ من هذه المعلومات.') }}
        </p>
    </form>
@endsection
