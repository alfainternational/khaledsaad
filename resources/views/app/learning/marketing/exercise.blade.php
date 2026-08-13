@extends('layouts.app')
@section('layout', 'detail')
@section('title', $exercise['title'])

@section('content')
    <header class="page-head learning-head">
        <div>
            <a href="{{ route('app.learning.marketing.home', ['project' => $project?->slug]) }}" class="learning-back">العودة إلى جميع الدروس</a>
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

            <form method="POST" action="{{ route('app.learning.marketing.course.save', ['exercise' => $exercise['key'], 'project' => $project?->slug]) }}" class="learning-answer-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="step" value="{{ $step }}">

                @if (($question['type'] ?? 'textarea') === 'number')
                    <input type="number" name="answer" min="{{ $question['min'] ?? 0 }}" value="{{ old('answer', $answer['value'] ?? '') }}" required autofocus>
                @else
                    <textarea name="answer" rows="8" required autofocus placeholder="{{ __('اكتب إجابتك هنا...') }}">{{ old('answer', $answer['value'] ?? '') }}</textarea>
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

                <section class="learning-ai-help" data-lesson-assist data-endpoint="{{ route('app.learning.marketing.course.assist', ['exercise' => $exercise['key'], 'question' => $question['key'], 'project' => $project?->slug]) }}">
                    <div class="learning-ai-help__head">
                        <div>
                            <p class="eyebrow">مساعدة مرتبطة بهذا السؤال</p>
                            <p>يقرأ المساعد الدرس كاملًا ومعيار هذا الحقل وإجاباتك المرتبطة قبل أن يقترح.</p>
                        </div>
                        <button type="button" class="btn btn--ghost" data-lesson-assist-run>اقترح بناءً على الدرس</button>
                    </div>
                    <p class="muted" data-lesson-assist-status role="status" aria-live="polite"></p>
                    <div class="learning-ai-help__result" data-lesson-assist-result hidden>
                        <span class="chip" data-lesson-assist-label>فرضية</span>
                        <p data-lesson-assist-help></p>
                        <p><strong>مثال مناسب لهذا الحقل:</strong> <span data-lesson-assist-example></span></p>
                        <p><strong>لماذا يناسب الدرس:</strong> <span data-lesson-assist-why></span></p>
                        <p><strong>خطوتك الآن:</strong> <span data-lesson-assist-action></span></p>
                        <details><summary>أساس المقترح</summary><ul data-lesson-assist-basis></ul></details>
                    </div>
                </section>

                <div class="learning-form-actions">
                    @if ($step > 1)
                        <a class="btn btn--ghost" href="{{ route('app.learning.marketing.course.exercise', ['exercise' => $exercise['key'], 'project' => $project?->slug, 'step' => $step - 1]) }}">السؤال السابق</a>
                    @endif
                    <button type="submit" class="btn btn--primary">{{ $step < $stepCount ? 'احفظ وانتقل للتالي' : 'احفظ الإجابة' }}</button>
                </div>
            </form>

            @if ($step === $stepCount)
                <form method="POST" action="{{ route('app.learning.marketing.course.submit', ['exercise' => $exercise['key'], 'project' => $project?->slug]) }}" class="learning-review-form">
                    @csrf
                    <button type="submit" class="btn btn--primary">راجع إجاباتي وقدّم النتيجة</button>
                    <small>تُقيَّم كل إجابة، ثم تصلك درجة للمهمة ومقترح واضح للتحسين.</small>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.querySelector('[data-lesson-assist]');
            if (!root) return;
            var button = root.querySelector('[data-lesson-assist-run]');
            var status = root.querySelector('[data-lesson-assist-status]');
            var result = root.querySelector('[data-lesson-assist-result]');
            button.addEventListener('click', function () {
                button.disabled = true;
                status.textContent = @js(__('نقرأ الدرس وهذا السؤال وإجاباتك المرتبطة…'));
                fetch(root.dataset.endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({})
                }).then(function (response) {
                    if (!response.ok) throw new Error('unavailable');
                    return response.json();
                }).then(function (payload) {
                    var data = payload.data;
                    root.querySelector('[data-lesson-assist-label]').textContent = data.evidence_label || @js(__('فرضية'));
                    root.querySelector('[data-lesson-assist-help]').textContent = data.field_help || '';
                    root.querySelector('[data-lesson-assist-example]').textContent = data.example || '';
                    root.querySelector('[data-lesson-assist-why]').textContent = data.why_it_fits || '';
                    root.querySelector('[data-lesson-assist-action]').textContent = data.next_action || '';
                    var list = root.querySelector('[data-lesson-assist-basis]');
                    list.replaceChildren();
                    (data.basis || []).forEach(function (item) {
                        var line = document.createElement('li');
                        line.textContent = item;
                        list.appendChild(line);
                    });
                    result.hidden = false;
                    status.textContent = data.notice || '';
                }).catch(function () {
                    status.textContent = @js(__('تعذرت المساعدة الآن. استخدم إرشاد السؤال الظاهر فوقها.'));
                }).finally(function () { button.disabled = false; });
            });
        });
    </script>
@endpush
