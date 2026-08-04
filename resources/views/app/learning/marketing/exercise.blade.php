@extends('layouts.app')
@section('layout', 'detail')
@section('title', $exercise['title'])

@section('content')
    <header class="page-head learning-head">
        <div>
            <a href="{{ route('app.learning.marketing.index', $project) }}" class="learning-back">العودة إلى جميع الدروس</a>
            <p class="eyebrow">الدرس {{ $exercise['lesson_number'] }} · {{ $exercise['lesson_title'] }}</p>
            <h1>{{ $exercise['title'] }}</h1>
            <p class="muted">{{ $exercise['purpose'] }}</p>
        </div>
        <div class="learning-progress" aria-label="موضعك في المهمة">
            <strong>{{ $step }} من {{ $stepCount }}</strong>
            <span>سؤال</span>
        </div>
    </header>

    @if (session('error'))
        <p class="alert alert--error" role="alert">{{ session('error') }}</p>
    @endif

    <div class="learning-step-layout">
        <section class="card learning-question" aria-labelledby="current-question">
            <div class="learning-step-bar" aria-hidden="true">
                <span style="width: {{ round(($step / $stepCount) * 100) }}%"></span>
            </div>
            <p class="eyebrow">السؤال {{ $step }}</p>
            <h2 id="current-question">{{ $question['label'] }}</h2>

            <form method="POST" action="{{ route('app.learning.marketing.save', [$project, $exercise['key']]) }}" class="learning-answer-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="step" value="{{ $step }}">

                @if (($question['type'] ?? 'textarea') === 'number')
                    <input type="number" name="answer" min="{{ $question['min'] ?? 0 }}" value="{{ old('answer', $answer['value'] ?? '') }}" required autofocus>
                @else
                    <textarea name="answer" rows="8" required autofocus placeholder="اكتب إجابتك هنا...">{{ old('answer', $answer['value'] ?? '') }}</textarea>
                @endif

                @error('answer')
                    <p class="field-error" role="alert">{{ $message }}</p>
                @enderror

                @if (($answer['source'] ?? null) === 'project')
                    <p class="learning-saved-note">أضفنا ما نعرفه عن مشروعك. راجعه وعدّله إن احتاج.</p>
                @elseif (($answer['source'] ?? null) === 'completed_exercise')
                    <p class="learning-saved-note">استفدنا من إجابة سابقة لك. راجعها قبل المتابعة.</p>
                @endif

                <details class="learning-help">
                    <summary>أحتاج مساعدة في الإجابة</summary>
                    <p>{{ $question['help'] }}</p>
                    <p><strong>مثال يساعدك:</strong> {{ $question['example'] }}</p>
                </details>

                <div class="learning-form-actions">
                    @if ($step > 1)
                        <a class="btn btn--ghost" href="{{ route('app.learning.marketing.exercise', [$project, $exercise['key'], 'step' => $step - 1]) }}">السؤال السابق</a>
                    @endif
                    <button type="submit" class="btn btn--primary">{{ $step < $stepCount ? 'احفظ وانتقل للتالي' : 'احفظ الإجابة' }}</button>
                </div>
            </form>

            @if ($step === $stepCount)
                <form method="POST" action="{{ route('app.learning.marketing.submit', [$project, $exercise['key']]) }}" class="learning-review-form">
                    @csrf
                    <button type="submit" class="btn btn--primary">راجع إجاباتي وقدّم النتيجة</button>
                    <small>سنقيّم كل إجابة ثم نعطيك درجة للمهمة ومقترحًا واضحًا للتحسين.</small>
                </form>
            @endif
        </section>

        <aside class="card learning-outcome">
            <p class="eyebrow">ما الذي ستحصل عليه</p>
            <h2>{{ $exercise['deliverable'] }}</h2>
            <p>بعد إرسال الإجابات ستحصل على درجة لكل إجابة، ودرجة للمهمة، وما نجح، وما يحتاج تحسينًا، وخطوة واحدة تبدأ بها.</p>
            <a href="{{ $exercise['source_url'] }}" target="_blank" rel="noopener">اقرأ شرح الدرس إذا احتجت</a>
        </aside>
    </div>
@endsection
