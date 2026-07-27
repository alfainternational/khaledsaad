@extends('layouts.app')

@section('title', 'التشخيصات')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">اختر الأولوية</p>
            <h1>ما الذي تريد تشخيصه الآن؟</h1>
            <p class="muted">اختر التحدي الأقرب إلى مشروعك. ستُستخدم معلوماتك المحفوظة لتقليل الأسئلة المتكررة.</p>
        </div>
    </header>

    <div class="card-grid">
        @foreach ($tools as $tool)
            @php($state = $engagements[$tool['key']] ?? null)

            <article @class(['card', 'card--muted' => ! $tool['is_runnable'], 'card--active' => ($state['state'] ?? 'new') !== 'new'])>
                <p class="eyebrow">{{ $tool['category'] }}</p>
                <h3>{{ $tool['title'] }}</h3>
                <p class="muted">{{ $tool['promise'] ?: $tool['description'] }}</p>

                @if (! $tool['is_runnable'])
                    <p class="badge">قريبًا</p>
                @elseif ($state && $state['state'] !== 'new')
                    {{-- من بدأ هنا يرى أين وقف، لا دعوة تمحو عمله. --}}
                    <p class="resume-hint">{{ $state['hint'] }}</p>

                    @if ($state['state'] === 'draft' && $state['percent'] > 0)
                        <div class="progress__bar progress__bar--slim">
                            <span style="inline-size: {{ $state['percent'] }}%"></span>
                        </div>
                    @endif

                    <div class="card__actions">
                        <a href="{{ $state['url'] }}" class="btn btn--primary btn--sm">{{ $state['label'] }}</a>

                        @if ($state['can_restart'])
                            <a href="{{ route('app.tools.show', $tool['key']) }}" class="btn btn--ghost btn--sm">ابدأ من جديد</a>
                        @endif
                    </div>
                @else
                    <a href="{{ route('app.tools.show', $tool['key']) }}" class="btn btn--ghost btn--sm">اعرف التفاصيل وابدأ</a>
                @endif
            </article>
        @endforeach
    </div>
@endsection
