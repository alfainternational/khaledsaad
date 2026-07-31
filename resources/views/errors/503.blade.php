@extends('layouts.public')
@section('layout', 'status')

@section('title', 'صيانة قصيرة | خالد سعد')
@section('description', 'المنصة في صيانة قصيرة مجدولة وستعود خلال دقائق.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">صيانة قصيرة</p>
                <h1>نجري تحسينًا سريعًا — دقائق ونعود.</h1>
                <p class="page-hero__lead">
                    المنصة في صيانة مجدولة قصيرة. بياناتك وتقاريرك سليمة تمامًا،
                    وكل شيء سيكون في مكانه حين تعود.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="location.reload()">جرّب الآن <span aria-hidden="true">←</span></button>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
