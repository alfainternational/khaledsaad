@extends('layouts.app')
@section('layout', 'detail')
@section('title', 'نتيجة '.$exercise['title'])

@if (in_array($attempt->status, ['queued', 'evaluating'], true))
    @push('head')
        <meta http-equiv="refresh" content="5">
    @endpush
@endif

@section('content')
    <header class="page-head learning-head">
        <div>
            <a href="{{ route('app.learning.marketing.home', ['project' => $project?->slug]) }}" class="learning-back">العودة إلى جميع الدروس</a>
            <p class="eyebrow">نتيجة المهمة</p>
            <h1>{{ $exercise['title'] }}</h1>
        </div>
    </header>

    @if (in_array($attempt->status, ['queued', 'evaluating'], true))
        <section class="card learning-waiting" aria-live="polite">
            <span class="learning-spinner" aria-hidden="true"></span>
            <h2>تُراجَع إجاباتك الآن</h2>
            <p>إجاباتك محفوظة. نقيّم كل واحدة ونجهز لك النتيجة والمقترحات، وستظهر هنا تلقائيًا.</p>
        </section>
    @elseif ($attempt->status === 'review_failed')
        <section class="card learning-waiting">
            <h2>حفظنا إجاباتك، لكن المراجعة لم تكتمل</h2>
            <p>لن تحتاج إلى كتابتها مرة أخرى. ابدأ المراجعة من جديد وسنحاول إكمال النتيجة.</p>
            <form method="POST" action="{{ route('app.learning.marketing.course.retry', ['exercise' => $exercise['key'], 'project' => $project?->slug]) }}">
                @csrf
                <button type="submit" class="btn btn--primary">أعد المراجعة</button>
            </form>
        </section>
    @elseif ($attempt->status === 'completed')
        <section class="learning-result-hero">
            <div class="learning-score" aria-label="{{ __('درجتك :score من 100', ['score' => $attempt->final_score]) }}">
                <strong>{{ $attempt->final_score }}</strong>
                <span>من 100</span>
            </div>
            <div>
                <p class="eyebrow">درجتك في المهمة</p>
                <h2>{{ $attempt->final_score >= 80 ? 'أساس قوي ويمكنك البناء عليه' : ($attempt->final_score >= 60 ? 'بداية جيدة وتحتاج بعض التوضيح' : 'الفكرة موجودة وتحتاج تفاصيل أكثر') }}</h2>
                <p>هذه الدرجة تجمع اكتمال إجاباتك وجودتها ومدى ترابطها مع النتيجة التي تريدها.</p>
            </div>
        </section>

        <div class="learning-result-grid">
            <section class="card" aria-labelledby="answer-grades">
                <p class="eyebrow">واحدة تلو الأخرى</p>
                <h2 id="answer-grades">تقييم إجاباتك</h2>
                <div class="learning-feedback-list">
                    @foreach ($exercise['questions'] as $question)
                        @php($item = $feedbackByKey->get($question['key']))
                        @if ($item)
                            <article class="learning-feedback">
                                <div class="learning-feedback__head">
                                    <h3>{{ $question['label'] }}</h3>
                                    <strong>{{ $item['score'] }}/100</strong>
                                </div>
                                <blockquote>{{ $attempt->answers[$question['key']] ?? '' }}</blockquote>
                                <p>{{ $item['comment'] }}</p>
                                <p class="learning-suggestion"><strong>كيف تحسنها:</strong> {{ $item['suggestion'] }}</p>
                            </article>
                        @endif
                    @endforeach
                </div>
            </section>

            <div class="layout-flow">
                <section class="card">
                    <p class="eyebrow">ما نجح</p>
                    <ul class="learning-points">
                        @foreach ($attempt->feedback['strengths'] ?? [] as $strength)
                            <li>{{ $strength }}</li>
                        @endforeach
                    </ul>
                </section>
                <section class="card">
                    <p class="eyebrow">ما يحتاج تحسينًا</p>
                    <ul class="learning-points">
                        @foreach ($attempt->feedback['improvements'] ?? [] as $improvement)
                            <li>{{ $improvement }}</li>
                        @endforeach
                    </ul>
                </section>
                <section class="card learning-next-action">
                    <p class="eyebrow">خطوتك التالية</p>
                    <h2>{{ $attempt->feedback['next_action'] ?? '' }}</h2>
                </section>
            </div>
        </div>

        <section class="card learning-deliverable" aria-labelledby="ready-output">
            <p class="eyebrow">المخرج الجاهز لك</p>
            <h2 id="ready-output">{{ $exercise['deliverable'] }}</h2>
            <div class="learning-deliverable__content">{{ $attempt->feedback['deliverable'] ?? '' }}</div>
        </section>

        @if ($reviewHistory->count() > 1)
            <section class="card learning-history" aria-labelledby="score-history">
                <p class="eyebrow">تطور إجاباتك</p>
                <h2 id="score-history">نتائجك السابقة في هذه المهمة</h2>
                <div class="learning-history__items">
                    @foreach ($reviewHistory as $review)
                        <article>
                            <strong>{{ $review->final_score }}/100</strong>
                            <span>المراجعة {{ $review->revision }}</span>
                            <time>{{ $review->reviewed_at->translatedFormat('j F Y، H:i') }}</time>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="learning-result-actions">
            <a href="{{ route('app.learning.marketing.course.exercise', ['exercise' => $exercise['key'], 'project' => $project?->slug]) }}" class="btn btn--ghost">حسّن إجاباتك</a>
            @if ($recommendation['exercise'] && $recommendation['exercise']['key'] !== $exercise['key'])
                <a href="{{ route('app.learning.marketing.course.exercise', ['exercise' => $recommendation['exercise']['key'], 'project' => $project?->slug]) }}" class="btn btn--primary">ابدأ المهمة المقترحة التالية</a>
            @else
                <a href="{{ route('app.learning.marketing.home', ['project' => $project?->slug]) }}" class="btn btn--primary">اختر المهمة التالية</a>
            @endif
        </div>
    @else
        <section class="card learning-waiting">
            <h2>المهمة لم تُرسل للمراجعة بعد</h2>
            <p>أكمل إجاباتك، ثم اطلب المراجعة لتحصل على الدرجة والمقترحات.</p>
            <a href="{{ route('app.learning.marketing.course.exercise', ['exercise' => $exercise['key'], 'project' => $project?->slug]) }}" class="btn btn--primary">أكمل الإجابات</a>
        </section>
    @endif
@endsection
