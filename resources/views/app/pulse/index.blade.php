@extends('layouts.app')
@section('layout', 'dashboard')

@section('title', 'النبض الأسبوعي')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">متابعة التحسين</p>
            <h1>النبض الأسبوعي</h1>
            <p class="muted">كل اثنين: ماذا تغيّر في مشاريعك، وما خطوة هذا الأسبوع.</p>
        </div>
    </header>

    @if ($weeks->isEmpty())
        <section class="empty">
            <h2>نبضك الأول في الطريق</h2>
            <p class="muted">
                يصدر النبض صباح كل اثنين تلقائيًا لكل مشاريعك: ما تغيّر، ما تأخر،
                وما الخطوة التي تستحق وقتك. لا تحتاج أن تفعل شيئًا — سيصلك إشعار.
            </p>
        </section>
    @else
        @foreach ($weeks as $week)
            <section class="pulse-week" aria-label="أسبوع {{ $week['week_start']->translatedFormat('j F Y') }}">
                <h2 class="section-title">
                    أسبوع {{ $week['week_start']->translatedFormat('j F Y') }}
                    @if ($loop->first)
                        <span class="badge">الأحدث</span>
                    @endif
                </h2>

                <div class="card-grid">
                    @foreach ($week['digests'] as $digest)
                        <article class="card">
                            <p class="eyebrow">
                                <a href="{{ route('app.projects.show', $digest->project->slug) }}">{{ $digest->project->name }}</a>
                            </p>

                            <ul class="pulse-items">
                                @foreach ($digest->items as $item)
                                    <li class="pulse-item pulse-item--{{ $item['type'] }}">
                                        <strong>{{ $item['title'] }}</strong>
                                        <p class="muted">{{ $item['body'] }}</p>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($digest->next_step)
                                <div class="next-step">
                                    <p class="eyebrow">خطوة الأسبوع</p>
                                    <strong>{{ $digest->next_step['title'] }}</strong>
                                    <p class="muted">{{ $digest->next_step['description'] }}</p>
                                    @if (! empty($digest->next_step['url']))
                                        <a href="{{ $digest->next_step['url'] }}" class="btn btn--primary btn--sm">ابدأ الآن</a>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
@endsection
