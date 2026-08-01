@extends('layouts.public')
@section('layout', 'reading')

@section('title', $page['title'].' | خالد سعد')
@section('description', $page['intro'])

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner">
                <p class="eyebrow">{{ $page['eyebrow'] }}</p>
                <h1>{{ $page['title'] }}</h1>
                <p class="page-hero__lead">{{ $page['intro'] }}</p>
                <p class="legal-updated">آخر تحديث: {{ $page['updated'] }}</p>
            </div>
        </section>

        <section class="section legal-section">
            <div class="container legal-body layout-page layout-page--reading">
                @foreach ($page['sections'] as $section)
                    <article class="legal-block">
                        <h2>{{ $section['title'] }}</h2>
                        @foreach ($section['paragraphs'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach

                        @if (! empty($section['points']))
                            <ul class="check-list">
                                @foreach ($section['points'] as $point)
                                    <li><span>✓</span> {{ $point }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach

                {{--
                    نصوص البنود نفسها تأتي من config/legal.php ولم تُمَسّ: أي
                    إعادة صياغة فيها تغيّر معنًى ملزمًا. المعدَّل هنا نداء
                    التواصل وحده، وهو خطابنا لا بند قانوني.
                --}}
                <div class="cta-band">
                    <p>بند غير واضح؟ اسألنا عنه وسنشرحه لك مباشرة.</p>
                    <a class="button button--primary" href="{{ $brand['contact']['whatsapp'] }}" target="_blank" rel="noopener noreferrer">اسأل عبر واتساب</a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer')
@endsection
