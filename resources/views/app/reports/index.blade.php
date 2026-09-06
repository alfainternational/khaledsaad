@extends('layouts.app')
@section('layout', 'index')

@section('title', __('تقاريري'))

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">{{ __('عبر مشاريعك كلها') }}</p>
            <h1>{{ __('تقاريري') }}</h1>
            <p class="muted">{{ __('كل تشخيص اكتمل، بدرجته وأداته وتاريخه.') }}</p>
        </div>
        <a href="{{ route('app.plan') }}" class="btn btn--ghost">{{ __('الخطة والمهام') }}</a>
    </header>

    @if ($projects->count() > 1)
        <nav class="filters" aria-label="{{ __('تصفية حسب المشروع') }}">
            <a href="{{ route('app.reports.index') }}"
               @class(['chip', 'is-active' => $active_project === ''])>{{ __('كل المشاريع') }}</a>
            @foreach ($projects as $project)
                <a href="{{ route('app.reports.index', ['project' => $project->slug]) }}"
                   @class(['chip', 'is-active' => $active_project === $project->slug])>{{ $project->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($cards === [])
        <x-ui.empty-state
            :title="__('لا تقارير بعد')"
            :description="__('شغّل تشخيصًا واحدًا، فيصلك تقرير بدرجتك وفجواتك وقائمة إصلاح مرتّبة بالأثر مقابل الجهد.')">
            <a href="{{ route('app.tools.index') }}" class="btn btn--primary">{{ __('ابدأ تشخيصًا') }}</a>
        </x-ui.empty-state>
    @else
        <ul class="report-list">
            @foreach ($cards as $card)
                <li class="report-list__item">
                    <a href="{{ route('app.reports.show', $card['id']) }}">
                        {{-- العنوان يُعرض مرة واحدة: تركيب «أحدث نتيجة: » قبله
                             ثم تكراره هو ما أنتج العنوان المزدوج في البطاقات. --}}
                        <strong>{{ $card['title'] }}</strong>
                    </a>

                    <p class="tags">
                        @if ($card['project']['slug'])
                            <span>{{ $card['project']['name'] }}</span>
                        @endif
                        <span>{{ $card['tool']['title'] }}</span>
                        @if ($card['score'] !== null)
                            {{-- التسمية صريحة: هذه درجة تشخيصٍ واحد، لا درجة
                                 جاهزية المشروع. عرضهما بلا اسمين هو العطل. --}}
                            <span>{{ __('درجة هذا التشخيص') }}: {{ \App\Support\Presentation\Num::score($card['score']) }}</span>
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>

        {{ $reports->links() }}
    @endif
@endsection
