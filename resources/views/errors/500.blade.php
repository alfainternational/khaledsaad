@extends('layouts.public')
@section('layout', 'status')

@section('title', 'حدث خلل مؤقت | خالد سعد')
@section('description', 'تعطّل شيء أثناء تنفيذ طلبك. أعد المحاولة بعد لحظات.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">خلل مؤقت من جهتنا</p>
                {{--
                    إقرار بلا استعطاف: يكفي أن يعرف القارئ أن العطل عندنا وأن
                    بياناته سليمة. التوسّع في الاعتذار يقرأه المستخدم شكًّا.
                --}}
                <h1>تعطّل شيء أثناء تنفيذ طلبك.</h1>
                <p class="page-hero__lead">
                    سُجّل الخلل تلقائيًا وسأعالجه. بياناتك وتقاريرك سليمة،
                    وغالبًا تنجح المحاولة التالية بعد لحظات.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="location.reload()">أعد المحاولة <span aria-hidden="true">←</span></button>
                    @auth
                        <a class="button button--ghost button--large" href="{{ route('app.dashboard') }}">افتح لوحتك</a>
                    @else
                        <a class="button button--ghost button--large" href="{{ route('home') }}">الصفحة الرئيسية</a>
                    @endauth
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
