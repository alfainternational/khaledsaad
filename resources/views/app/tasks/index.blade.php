@extends('layouts.app')

@section('title', 'المهام')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ $project['name'] }}</p>
            <h1>من التوصية إلى التنفيذ</h1>
            <p class="muted">كل مهمة هنا جاءت من توصية في تقرير، ومعها أثرها وجهدها وموعدها.</p>
        </div>
        <a href="{{ route('app.projects.show', $project['slug']) }}" class="btn btn--ghost">عودة للمشروع</a>
    </header>

    @if ($tasks['todo'] === [] && $tasks['doing'] === [] && $tasks['done'] === [])
        <section class="empty">
            <h2>لا مهام بعد</h2>
            <p>افتح أي تقرير وحوّل توصياته إلى مهام — هنا يتحول التحليل إلى عمل.</p>
        </section>
    @else
        <div class="board">
            @foreach (['todo' => 'لم تبدأ', 'doing' => 'قيد التنفيذ', 'done' => 'منجزة'] as $key => $label)
                <section class="board__column" aria-labelledby="column-{{ $key }}">
                    <h2 id="column-{{ $key }}">{{ $label }} ({{ count($tasks[$key]) }})</h2>

                    @forelse ($tasks[$key] as $task)
                        <article @class(['task', 'task--overdue' => $task['is_overdue']])>
                            <strong>{{ $task['title'] }}</strong>

                            @if ($task['description'])
                                <p class="muted">{{ $task['description'] }}</p>
                            @endif

                            <p class="tags">
                                @if ($task['due_date'])
                                    <span>{{ $task['is_overdue'] ? 'تأخرت عن' : 'حتى' }} {{ $task['due_date'] }}</span>
                                @endif
                                @if ($task['impact'])
                                    <span>الأثر: {{ $task['impact'] }}</span>
                                @endif
                                @if ($task['effort'])
                                    <span>الجهد: {{ $task['effort'] }}</span>
                                @endif
                            </p>

                            <form method="POST" action="{{ route('app.tasks.update', $task['id']) }}" class="inline-form">
                                @csrf
                                @method('PATCH')
                                <label class="sr-only" for="status-{{ $task['id'] }}">حالة {{ $task['title'] }}</label>
                                <select id="status-{{ $task['id'] }}" name="status">
                                    <option value="todo" @selected($task['status'] === 'todo')>لم تبدأ</option>
                                    <option value="doing" @selected($task['status'] === 'doing')>قيد التنفيذ</option>
                                    <option value="done" @selected($task['status'] === 'done')>منجزة</option>
                                </select>
                                <button type="submit" class="btn btn--ghost btn--sm">حدّث</button>
                            </form>
                        </article>
                    @empty
                        <p class="muted">لا شيء هنا.</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif
@endsection
