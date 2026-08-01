@extends('layouts.public')
@section('layout', 'status')

@section('title', 'طلبات متسارعة | خالد سعد')
@section('description', 'وصلت طلبات كثيرة من جهازك في وقت قصير. انتظر نحو دقيقة ثم أعد المحاولة.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">توقّف مؤقت</p>
                <h1>طلبات كثيرة في وقت قصير.</h1>
                {{--
                    السبب ثم الفعل: من يعرف أن التوقف مؤقت وسببه معروف ينتظر
                    دقيقة، ومن لا يعرف يعيد المحاولة فورًا فيطيل التوقف.
                --}}
                <p class="page-hero__lead">
                    وصلت طلبات كثيرة من جهازك خلال وقت قصير، فأوقفناها مؤقتًا لحماية الخدمة.
                    انتظر نحو دقيقة ثم أعد المحاولة. لم يضع شيء مما أرسلته.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="location.reload()">أعد المحاولة <span aria-hidden="true">←</span></button>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
