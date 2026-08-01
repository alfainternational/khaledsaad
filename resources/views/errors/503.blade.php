@extends('layouts.public')
@section('layout', 'status')

@section('title', 'صيانة قصيرة | خالد سعد')
@section('description', 'المنصة في صيانة مجدولة الآن. أعد التحميل بعد قليل.')

@section('content')
    @include('partials.site-header')

    <main id="main-content">
        <section class="page-hero">
            <div class="container page-hero__inner status-layout">
                <p class="eyebrow">صيانة قصيرة</p>
                {{--
                    بلا وعد بمدة: «دقائق ونعود» رقم لا نضمنه، وخلفه المستخدم
                    يعيد التحميل ثم يشعر أننا كذبنا عليه.
                --}}
                <h1>المنصة في صيانة مجدولة الآن.</h1>
                <p class="page-hero__lead">
                    نحدّث المنصة، والصفحات لا تعمل أثناء التحديث. بياناتك وتقاريرك سليمة
                    وكل شيء في مكانه حين تعود. أعد التحميل بعد قليل.
                </p>

                <div class="page-hero__actions">
                    <button type="button" class="button button--primary button--large" onclick="location.reload()">أعد التحميل <span aria-hidden="true">←</span></button>
                </div>
            </div>
        </section>
    </main>

    @include('partials.site-footer', ['brand' => config('brand')])
@endsection
