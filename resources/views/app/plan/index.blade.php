@extends('layouts.app')
@section('layout', 'board')

@section('title', __('الخطة والمهام'))

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ __('عبر مشاريعك كلها') }}</p>
            <h1>{{ __('الخطة والمهام') }}</h1>
            <p class="muted">
                {{ __('كل مهمة هنا جاءت من توصية في تقرير، ومعها أثرها وجهدها وموعدها.') }}
            </p>
        </div>
        <a href="{{ route('app.reports.index') }}" class="btn btn--ghost">{{ __('تقاريري') }}</a>
    </header>

    @if ($total === 0)
        {{-- الفراغ يشرح ما سيظهر ولماذا يستحق، ويقدّم إجراءً واحدًا:
             «٠ مهام» وحدها تُهدر أثمن لحظة تعليم في المنتج. --}}
        <x-ui.empty-state
            :title="__('لا مهام بعد')"
            :description="$projects_count === 0
                ? __('أضف مشروعك الأول، ثم شغّل تشخيصًا — تتحول توصياته إلى خطة أسبوعية تتابعها هنا.')
                : __('شغّل تشخيصًا — تتحول توصياته إلى مهام مقترحة هنا تلقائيًّا، وتتبنّى منها ما تريد.')">
            <a href="{{ $projects_count === 0 ? route('app.projects.create') : route('app.reports.index') }}"
               class="btn btn--primary">
                {{ $projects_count === 0 ? __('أضف مشروعًا') : __('افتح تقاريري') }}
            </a>
        </x-ui.empty-state>
    @else
        @foreach ($groups as $key => $group)
            @continue($group['tasks'] === [])

            <section class="plan-group" aria-labelledby="plan-{{ $key }}">
                <h2 id="plan-{{ $key }}" class="section-title">
                    {{ $group['label'] }} ({{ \App\Support\Presentation\Num::int(count($group['tasks'])) }})
                </h2>
                <p class="muted">{{ $group['hint'] }}</p>

                <ul class="plan-list">
                    @foreach ($group['tasks'] as $task)
                        <li @class(['task', 'task--overdue' => $task['is_overdue']])>
                            <strong>{{ $task['title'] }}</strong>

                            @if ($task['project']['slug'])
                                <a class="task__project"
                                   href="{{ route('app.projects.tasks', $task['project']['slug']) }}">
                                    {{ $task['project']['name'] }}
                                </a>
                            @endif

                            @if ($task['is_suggested'] ?? false)
                                {{-- التبنّي فعلٌ واحد: الخطة تُقترح كاملة،
                                     ويلتزم منها بما يريد. --}}
                                <form method="POST" action="{{ route('app.tasks.adopt', $task['id']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn--sm btn--primary">
                                        {{ __('أضِفها إلى خطتي') }}
                                    </button>
                                </form>
                            @endif

                            <p class="tags">
                                <span>{{ $task['status_label'] }}</span>
                                @if ($task['report_id'])
                                    <a href="{{ route('app.reports.show', $task['report_id']) }}">
                                        {{ __('من هذا التقرير') }}
                                    </a>
                                @endif
                                @if ($task['due_date'])
                                    <span>
                                        {{ $task['is_overdue'] ? __('تأخرت عن') : __('حتى') }} {{ $task['due_date'] }}
                                    </span>
                                @endif
                                @if ($task['impact'])<span>{{ __('الأثر') }}: {{ $task['impact'] }}</span>@endif
                                @if ($task['effort'])<span>{{ __('الجهد') }}: {{ $task['effort'] }}</span>@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
@endsection
