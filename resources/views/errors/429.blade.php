@extends('layouts.public')
@section('layout', 'status')

@section('title', 'طلبات متسارعة | خالد سعد')
@section('description', 'وصلت طلبات كثيرة متتالية. انتظر لحظات ثم أعد المحاولة.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">تمهّل قليلًا</p>
                <h1>طلبات كثيرة في وقت قصير.</h1>
                <p class="page-hero__lead">
                    حماية تلقائية أبطأت الطلبات مؤقتًا. انتظر نحو دقيقة ثم أعد
                    المحاولة — لا شيء ضاع ولا حاجة لأي إجراء آخر.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="location.reload()">أعد المحاولة <span aria-hidden="true">←</span></button>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
