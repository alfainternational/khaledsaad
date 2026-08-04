@extends('layouts.app')
@section('layout', 'index')
@section('title', 'تطبيق دروس التسويق')

@section('content')
    <header class="page-head learning-head">
        <div>
            <p class="eyebrow">مشروع {{ $project->name }}</p>
            <h1>طبّق التسويق على مشروعك</h1>
            <p class="muted">أجب عن أسئلة قصيرة، وسنراجع كل إجابة ونجهز لك نتيجة يمكنك استخدامها.</p>
        </div>
        <div class="learning-progress" aria-label="تقدمك في المهام">
            <strong>{{ $run->completed_exercises }} من {{ $exerciseCount }}</strong>
            <span>مهمة مكتملة</span>
        </div>
    </header>

    @if ($recommendation['exercise'])
        <section class="learning-recommendation" aria-labelledby="recommended-task">
            <div>
                <p class="eyebrow">ابدأ من هنا</p>
                <h2 id="recommended-task">{{ $recommendation['exercise']['title'] }}</h2>
                <p>{{ $recommendation['reason'] }}</p>
                <ul class="learning-meta" aria-label="تفاصيل المهمة">
                    <li>تحتاج نحو {{ $recommendation['exercise']['duration_minutes'] }} دقيقة</li>
                    <li>ستحصل على: {{ $recommendation['exercise']['deliverable'] }}</li>
                </ul>
            </div>
            <a class="btn btn--primary" href="{{ route('app.learning.marketing.exercise', [$project, $recommendation['exercise']['key']]) }}">
                {{ $run->current_exercise_key === $recommendation['exercise']['key'] ? 'أكمل المهمة' : 'ابدأ المهمة' }}
            </a>
        </section>
    @else
        <section class="learning-recommendation learning-recommendation--done">
            <div>
                <p class="eyebrow">أحسنت</p>
                <h2>أكملت جميع المهام</h2>
                <p>نتائجك محفوظة، ويمكنك فتح أي مهمة لتحسين إجاباتك ورفع درجتك.</p>
            </div>
        </section>
    @endif

    <section aria-labelledby="course-lessons">
        <div class="learning-section-head">
            <div>
                <p class="eyebrow">المسار الكامل</p>
                <h2 id="course-lessons">الدروس العشرون ومهامها</h2>
            </div>
            <p class="muted">يمكنك اتباع ترتيبنا المقترح أو اختيار أي مهمة بنفسك.</p>
        </div>

        <div class="learning-lessons">
            @foreach ($lessons as $lesson)
                @php($completedInLesson = collect($lesson['exercises'])->where('status', 'completed')->count())
                <details class="learning-lesson" @if ($loop->first) open @endif>
                    <summary>
                        <span class="learning-lesson__number">الدرس {{ $lesson['number'] }}</span>
                        <strong>{{ $lesson['title'] }}</strong>
                        <small>{{ $completedInLesson }} من {{ count($lesson['exercises']) }} مكتملة</small>
                    </summary>
                    <div class="learning-task-list">
                        @foreach ($lesson['exercises'] as $task)
                            <article class="learning-task">
                                <div>
                                    <h3>{{ $task['title'] }}</h3>
                                    <p>{{ $task['purpose'] }}</p>
                                    <small>{{ $task['duration_minutes'] }} دقيقة · {{ $task['status_label'] }}</small>
                                </div>
                                <div class="learning-task__action">
                                    @if ($task['score'] !== null)
                                        <strong class="score-chip">{{ $task['score'] }}/100</strong>
                                    @endif
                                    <a href="{{ in_array($task['status'], ['completed', 'queued', 'evaluating', 'review_failed'], true)
                                        ? route('app.learning.marketing.result', [$project, $task['key']])
                                        : route('app.learning.marketing.exercise', [$project, $task['key']]) }}" class="btn btn--ghost btn--sm">
                                        @if ($task['status'] === 'completed')
                                            افتح النتيجة
                                        @elseif (in_array($task['status'], ['queued', 'evaluating'], true))
                                            تابع المراجعة
                                        @elseif ($task['status'] === 'review_failed')
                                            أعد المراجعة
                                        @elseif ($task['status'] === 'draft')
                                            أكمل
                                        @else
                                            ابدأ
                                        @endif
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <a href="{{ $lesson['source_url'] }}" target="_blank" rel="noopener" class="learning-source">اقرأ شرح الدرس عند الحاجة</a>
                </details>
            @endforeach
        </div>
    </section>
@endsection
